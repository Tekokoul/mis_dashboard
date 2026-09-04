"""
Meaning matcher: turns a short piece of text into a list of numbers whose
closeness reflects sense rather than shared words, so "conference sign-ups"
lands near "CPHIA Registrations".

Runs entirely on this machine. The model is baked into the image at build
time, so nothing is fetched at run time, and the container sits on a Docker
network declared `internal: true` with no published port - so it has no route
off the machine even if something here tried. No account, no key, no quota.

  GET  /health -> {"ok": true, "model": ..., "dim": 384}
  POST /embed  {"texts": [...], "kind": "query"|"passage"}
               -> {"vectors": [[...]], "dim": 384, "model": ..., "ms": 12}

Only the standard library plus onnxruntime, tokenizers and numpy: no web
framework and above all no PyTorch, which would add 650 MB for nothing.
"""
import json
import logging
import os
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import numpy as np
import onnxruntime as ort
from tokenizers import Tokenizer

MODEL_DIR = os.environ.get("MODEL_DIR", "/model")
MODEL_ID = os.environ.get("MODEL_ID", "unknown")
PORT = int(os.environ.get("PORT", "8077"))
THREADS = int(os.environ.get("ORT_THREADS", "2"))
MAX_TOKENS = int(os.environ.get("MAX_TOKENS", "192"))
MAX_BATCH = int(os.environ.get("MAX_BATCH", "128"))
# e5 was trained with these markers; a model that does not use them sets
# EMBED_PREFIXES=0 and the text is passed through unchanged.
PREFIXES = os.environ.get("EMBED_PREFIXES", "1") == "1"

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("matcher")

tokenizer = Tokenizer.from_file(os.path.join(MODEL_DIR, "tokenizer.json"))
tokenizer.enable_truncation(max_length=MAX_TOKENS)
tokenizer.enable_padding()

_opts = ort.SessionOptions()
# Two threads, not four: this sits next to PHP-FPM and MariaDB on a small box,
# and a four-thread pool is only ~46% efficient on this shape of model anyway.
_opts.intra_op_num_threads = THREADS
_opts.inter_op_num_threads = 1
_opts.graph_optimization_level = ort.GraphOptimizationLevel.ORT_ENABLE_ALL
session = ort.InferenceSession(os.path.join(MODEL_DIR, "model.onnx"), _opts, providers=["CPUExecutionProvider"])
WANTED = {i.name for i in session.get_inputs()}
DIM = session.get_outputs()[0].shape[-1]
if not isinstance(DIM, int):
    DIM = 0
_lock = threading.Lock()


def embed(texts, kind):
    marker = ("query: " if kind == "query" else "passage: ") if PREFIXES else ""
    encoded = tokenizer.encode_batch([marker + (t or "") for t in texts])
    ids = np.array([e.ids for e in encoded], dtype=np.int64)
    mask = np.array([e.attention_mask for e in encoded], dtype=np.int64)
    feed = {"input_ids": ids, "attention_mask": mask}
    if "token_type_ids" in WANTED:
        feed["token_type_ids"] = np.zeros_like(ids)
    feed = {k: v for k, v in feed.items() if k in WANTED}
    with _lock:
        hidden = session.run(None, feed)[0]
    # Average over the real tokens only, then scale to unit length so a plain
    # dot product is the cosine.
    m = mask[..., None].astype(np.float32)
    pooled = (hidden * m).sum(axis=1) / np.clip(m.sum(axis=1), 1e-9, None)
    norm = np.linalg.norm(pooled, axis=1, keepdims=True)
    return (pooled / np.clip(norm, 1e-9, None)).astype(np.float32)


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, *args):  # the access log would just be noise
        pass

    def _send(self, code, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path.rstrip("/") in ("/health", ""):
            self._send(200, {"ok": True, "model": MODEL_ID, "dim": DIM, "max_tokens": MAX_TOKENS})
        else:
            self._send(404, {"error": "not found"})

    def do_POST(self):
        if self.path.rstrip("/") != "/embed":
            self._send(404, {"error": "not found"})
            return
        try:
            length = int(self.headers.get("Content-Length") or 0)
            if length <= 0 or length > 4_000_000:
                self._send(413, {"error": "body missing or too large"})
                return
            payload = json.loads(self.rfile.read(length) or b"{}")
            texts = payload.get("texts") or []
            if not isinstance(texts, list) or not texts:
                self._send(400, {"error": "texts must be a non-empty list"})
                return
            if len(texts) > MAX_BATCH:
                self._send(413, {"error": "at most %d texts per call" % MAX_BATCH})
                return
            started = time.time()
            vectors = embed([str(t)[:8000] for t in texts], payload.get("kind") or "query")
            self._send(200, {
                "vectors": [v.tolist() for v in vectors],
                "dim": int(vectors.shape[1]),
                "model": MODEL_ID,
                "ms": int((time.time() - started) * 1000),
            })
        except Exception as exc:  # a bad request must not take the sidecar down
            log.exception("embed failed")
            self._send(500, {"error": type(exc).__name__ + ": " + str(exc)[:200]})


if __name__ == "__main__":
    log.info("model=%s dim=%s inputs=%s threads=%d", MODEL_ID, DIM, sorted(WANTED), THREADS)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()

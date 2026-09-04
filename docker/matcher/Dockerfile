# The meaning matcher. Two stages so the model is fetched once, at build time,
# on a machine that has internet: the runtime image carries the weights and
# never needs the network again.
ARG MODEL_ID=intfloat/multilingual-e5-small
ARG MODEL_REV=614241f622f53c4eeff9890bdc4f31cfecc418b3

FROM python:3.12-slim AS weights
ARG MODEL_ID
ARG MODEL_REV
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /model
# Pinned to a revision, so the same build produces the same model every time.
RUN set -eux; \
    base="https://huggingface.co/${MODEL_ID}/resolve/${MODEL_REV}"; \
    for f in onnx/model.onnx tokenizer.json config.json tokenizer_config.json special_tokens_map.json; do \
        out="/model/$(basename "$f")"; \
        for attempt in 1 2 3 4 5; do \
            if curl -fsSL --retry 3 --retry-delay 4 -o "$out" "${base}/${f}"; then break; fi; \
            echo "retry ${attempt} for ${f}"; sleep 6; \
        done; \
        test -s "$out"; \
    done; \
    ls -la /model

FROM python:3.12-slim
ARG MODEL_ID
LABEL org.opencontainers.image.title="AfCDC DHIS meaning matcher"
LABEL org.opencontainers.image.description="Local sentence embeddings for filing activities. Runs on an internal-only Docker network."
ENV PYTHONUNBUFFERED=1 \
    HF_HUB_OFFLINE=1 \
    TRANSFORMERS_OFFLINE=1 \
    HF_HUB_DISABLE_TELEMETRY=1 \
    DO_NOT_TRACK=1 \
    OMP_NUM_THREADS=2 \
    MODEL_DIR=/model
ENV MODEL_ID=${MODEL_ID}
# --no-cache-dir keeps the layer small; these three are the whole dependency
# list, and none of them pulls in PyTorch.
RUN pip install --no-cache-dir "onnxruntime==1.23.2" "tokenizers==0.22.1" "numpy==2.3.5" \
    && find /usr/local/lib/python3.12/site-packages -name '__pycache__' -type d -prune -exec rm -rf {} + \
    && useradd --system --uid 10077 --no-create-home matcher
COPY --from=weights /model /model
COPY server.py /app/server.py
USER matcher
EXPOSE 8077
HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=180s \
    CMD python -c "import urllib.request,sys; sys.exit(0 if __import__('json').load(urllib.request.urlopen('http://127.0.0.1:8077/health',timeout=4)).get('ok') else 1)"
CMD ["python", "/app/server.py"]

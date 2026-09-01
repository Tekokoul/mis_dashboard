#!/usr/bin/env python3
"""
Extract the Porto base-theme chrome rules and re-emit them in AU colours.

Why this exists: the sidebar, top bar and logo plate are painted by literals
that live in theme.css and modern.css, NOT in the skin file. tools/skin-source.css and
its siblings only ever varied the four Porto brand slots, so re-hueing the skin
leaves the application chrome the original near-black slate. Overriding it by
hand would mean tracking 56+ declarations across two vendored files.

Instead this walks theme.css and modern.css, keeps every rule block that paints
one of the chrome darks, and re-emits just those declarations with the colour
mapped through the same transform the skin uses. The output is appended to
skin_africacdc.css, which the template already loads after both source files,
so the selectors match exactly and the later declaration wins on cascade order
without any specificity games.

Imported by make_skin_africacdc.py; not run directly.
"""
import re

CHROME_DARKS = {"#1d2127", "#191c21", "#121518", "#282d36", "#0f1114", "#171a1f"}

# A declaration is kept only if it is a colour-bearing property. Re-emitting
# layout properties would duplicate geometry and risk drift from the vendor file.
COLOUR_PROP = re.compile(
    r"^\s*(background|background-color|background-image|border|border-color|"
    r"border-top-color|border-right-color|border-bottom-color|border-left-color|"
    r"border-top|border-right|border-bottom|border-left|color|box-shadow|"
    r"outline-color|fill|stroke)\s*:",
    re.I,
)

def _tokenize(css):
    """Yield (kind, payload) for at-rule wrappers and plain rule blocks."""
    i, n, depth, buf, stack = 0, len(css), 0, [], []
    out = []
    # strip comments first so braces inside them cannot desync the scanner
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    n = len(css)
    i = 0
    prelude = []
    while i < n:
        ch = css[i]
        if ch == "{":
            head = "".join(prelude).strip()
            prelude = []
            if head.startswith("@") and not head.startswith("@font-face"):
                stack.append(head)
                out.append(("enter", head))
            else:
                # consume to the matching close brace
                d, j = 1, i + 1
                while j < n and d:
                    if css[j] == "{":
                        d += 1
                    elif css[j] == "}":
                        d -= 1
                    j += 1
                out.append(("rule", (tuple(stack), head, css[i + 1 : j - 1])))
                i = j
                continue
        elif ch == "}":
            if stack:
                stack.pop()
                out.append(("exit", None))
            prelude = []
        else:
            prelude.append(ch)
        i += 1
    return out

def extract(path, convert_hex):
    """Return CSS text: the chrome rules from `path`, re-coloured."""
    css = open(path, encoding="utf-8").read()
    blocks = []
    for kind, payload in _tokenize(css):
        if kind != "rule":
            continue
        media, selector, body = payload
        if not any(d in body.lower() for d in CHROME_DARKS):
            continue
        kept = []
        for decl in body.split(";"):
            if not decl.strip() or not COLOUR_PROP.match(decl):
                continue
            if not any(d in decl.lower() for d in CHROME_DARKS):
                continue
            kept.append(re.sub(r"#[0-9a-fA-F]{6}\b", convert_hex, decl).strip())
        if kept:
            blocks.append((media, selector, kept))
    if not blocks:
        return ""

    out = [f"\n/* ---- chrome extracted from {path.split('/')[-1]} ---- */\n"]
    current = ()
    for media, selector, kept in blocks:
        if media != current:
            for _ in current:
                out.append("}\n")
            for m in media:
                out.append(m + " {\n")
            current = media
        indent = "  " * len(current)
        out.append(f"{indent}{selector} {{\n")
        for d in kept:
            out.append(f"{indent}  {d};\n")
        out.append(indent + "}\n")
    for _ in current:
        out.append("}\n")
    return "".join(out)

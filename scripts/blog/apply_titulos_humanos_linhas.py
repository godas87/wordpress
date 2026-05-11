# -*- coding: utf-8 -*-
"""Aplica títulos de titulos_humanos_64linhas.txt aos artigos (JSON + primeira linha H1 dos .md).

Ordem das linhas = sorted(rglob(\"artigo_*.json\")) relativo a scripts/blog.
"""
from pathlib import Path
import json
import subprocess
import sys

BASE = Path(__file__).resolve().parent
TXT = BASE / "titulos_humanos_64linhas.txt"


def set_titulo(obj, novo):
    if isinstance(obj, dict):
        if "titulo" in obj and isinstance(obj["titulo"], str):
            obj["titulo"] = novo
            return True
        for v in obj.values():
            if set_titulo(v, novo):
                return True
    elif isinstance(obj, list):
        for item in obj:
            if set_titulo(item, novo):
                return True
    return False


def main():
    texto = TXT.read_text(encoding="utf-8")
    linhas = [ln.strip() for ln in texto.splitlines() if ln.strip()]
    json_paths = sorted(BASE.rglob("artigo_*.json"))

    if len(linhas) != len(json_paths):
        print(
            "ERRO: contagem diferente:",
            len(linhas),
            "linhas vs",
            len(json_paths),
            "JSON.",
            file=sys.stderr,
        )
        sys.exit(1)

    for path, tit in zip(json_paths, linhas):
        data = json.loads(path.read_text(encoding="utf-8"))
        if not set_titulo(data, tit):
            print(
                'ERRO: campo "titulo" não encontrado em:',
                path.relative_to(BASE),
                file=sys.stderr,
            )
            sys.exit(1)
        path.write_text(
            json.dumps(data, ensure_ascii=False, indent=2, sort_keys=False) + "\n",
            encoding="utf-8",
        )

        md = path.with_suffix(".md")
        if md.is_file():
            body = md.read_text(encoding="utf-8")
            if body.lstrip().startswith("#"):
                resto = body.split("\n", 1)[1] if "\n" in body else ""
                md.write_text("# " + tit + "\n" + resto, encoding="utf-8")
            else:
                md.write_text("# " + tit + "\n\n" + body.lstrip(), encoding="utf-8")

    subprocess.run(
        [sys.executable, str(BASE / "gerar-manifesto-blog.py")],
        cwd=str(BASE),
        check=True,
    )
    print("OK:", len(json_paths), "artigos atualizados + manifesto gerado.")


if __name__ == "__main__":
    main()

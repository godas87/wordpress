#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Gera scripts/blog/blog-manifesto.json a partir de todos os artigo_*.json
em subpastas de scripts/blog, com caminhos explícitos para .md e capa,
taxonomias mínimas (SEO + WP), nó "outlines" (subtítulos para planeamento)
e fila editorial por round-robin entre verticais.

Outlines: chaves "outline 1", "outline 2", … no JSON (MANUS); se não houver,
extraídos dos cabeçalhos ## no ficheiro .md.

Uso:
  python scripts/blog/gerar-manifesto-blog.py

Requisitos: apenas biblioteca padrão.
"""

from __future__ import annotations

import json
import random
import re
from collections import defaultdict, deque
from datetime import datetime, timezone
from pathlib import Path

BLOG_ROOT = Path(__file__).resolve().parent
THEME_SCRIPTS_BLOG = "scripts/blog"
MANIFEST_NAME = "blog-manifesto.json"
BACKUP_SUFFIX = ".backup.json"
SEED = 42
IMG_EXT = ("webp", "png", "jpg", "jpeg", "gif")


def slugify(text: str, max_len: int = 180) -> str:
    t = text.lower().strip()
    t = re.sub(r"[^\w\s-]", "", t, flags=re.UNICODE)
    t = re.sub(r"[-\s]+", "-", t).strip("-")
    return t[:max_len] if t else "post"


def infer_tipo_artigo(stem: str) -> str:
    s = stem.lower()
    if "artigo_pilar_" in s or s.startswith("artigo_pilar"):
        return "Pilar"
    if "artigo_cluster_" in s or s.startswith("artigo_cluster"):
        return "Cluster"
    if "artigo_orfao_" in s or s.startswith("artigo_orfao"):
        return "Orfão"
    return ""


def normalize_taxonomy_row(row: dict) -> dict | None:
    tipo = row.get("tipo") or row.get("taxonomy")
    if not tipo:
        return None
    out: dict = {"tipo": str(tipo).strip()}
    if row.get("slug"):
        out["slug"] = str(row["slug"]).strip()
    if row.get("id") is not None and str(row["id"]).isdigit():
        out["id"] = int(row["id"])
    return out


def flatten_json_articles(data, json_path: Path) -> list[dict]:
    """Extrai lista de dicts de artigo (metadados crus) a partir do JSON."""
    vertical = json_path.parent.name
    out: list[dict] = []

    if isinstance(data, list) and data and isinstance(data[0], dict) and "blog" in data[0]:
        for wrap in data:
            for item in wrap.get("blog") or []:
                if isinstance(item, dict):
                    out.append(dict(item))
        return out

    if isinstance(data, list):
        for item in data:
            if isinstance(item, dict):
                out.append(dict(item))
        return out

    if isinstance(data, dict):
        out.append(dict(data))
        return out

    return out


def resolve_capa_path(json_dir: Path, stem: str, raw_items: list[dict]) -> str | None:
    """Devolve caminho relativo ao tema (scripts/blog/...) ou None."""
    rel_prefix = f"{THEME_SCRIPTS_BLOG}/{json_dir.name}"

    for ext in IMG_EXT:
        p = json_dir / f"{stem}.{ext}"
        if p.is_file():
            return f"{rel_prefix}/{stem}.{ext}"

    for item in raw_items:
        capa = item.get("capa")
        if isinstance(capa, str) and capa.strip():
            c = capa.strip().replace("\\", "/")
            if c.startswith(THEME_SCRIPTS_BLOG + "/"):
                return c
            cand = json_dir / c.lstrip("/")
            if cand.is_file():
                return f"{rel_prefix}/{cand.name}"

        img = item.get("imagem_capa")
        if isinstance(img, dict):
            arq = (img.get("arquivo") or "").strip()
            if arq:
                cand = json_dir / arq
                if cand.is_file():
                    return f"{rel_prefix}/{cand.name}"
        elif isinstance(img, str) and img.strip():
            cand = json_dir / img.strip().lstrip("/")
            if cand.is_file():
                return f"{rel_prefix}/{cand.name}"

    return None


def outlines_from_json_raw(raw: dict) -> list[str]:
    """Subtítulos vindos do MANUS: outline 1, outline 2, … (ordem numérica)."""
    pairs: list[tuple[int, str]] = []
    for k, v in raw.items():
        if not isinstance(k, str) or not isinstance(v, str):
            continue
        m = re.match(r"^outline\s+(\d+)$", k.strip(), re.IGNORECASE)
        if not m:
            continue
        t = v.strip()
        if t:
            pairs.append((int(m.group(1)), t))
    pairs.sort(key=lambda x: x[0])
    return [p[1] for p in pairs]


def outlines_from_markdown_file(md_path: Path) -> list[str]:
    """Fallback: linhas ## (H2) do Markdown, na ordem do ficheiro."""
    try:
        text = md_path.read_text(encoding="utf-8")
    except OSError:
        return []
    out: list[str] = []
    for line in text.splitlines():
        m = re.match(r"^##\s+(.+)$", line.rstrip())
        if m:
            out.append(m.group(1).strip())
    return out


def collect_outlines(raw: dict, md_path: Path) -> list[str]:
    from_json = outlines_from_json_raw(raw)
    if from_json:
        return from_json
    return outlines_from_markdown_file(md_path)


def build_manifest_item(
    json_path: Path, raw: dict, stem: str, vertical: str
) -> dict | None:
    md = json_path.with_suffix(".md")
    if not md.is_file():
        return None

    titulo = (raw.get("titulo") or "").strip()
    if not titulo:
        return None

    meta = (
        raw.get("descricao_seo")
        or raw.get("descricao")
        or raw.get("meta_description")
        or ""
    )
    meta = str(meta).strip()

    tipo_artigo = (raw.get("tipo_artigo") or infer_tipo_artigo(stem)).strip()
    slug = (
        raw.get("slug_sugerido")
        or raw.get("slug")
        or slugify(titulo)
    )
    slug = str(slug).strip()

    tax_raw = raw.get("taxonomias") or []
    taxonomias = []
    if isinstance(tax_raw, list):
        for row in tax_raw:
            if isinstance(row, dict):
                n = normalize_taxonomy_row(row)
                if n:
                    taxonomias.append(n)

    json_dir = json_path.parent
    raw_list = [raw]
    capa_rel = resolve_capa_path(json_dir, stem, raw_list)

    bundle_id = f"{vertical}/{stem}"
    outlines = collect_outlines(raw, md)

    return {
        "id": bundle_id,
        "vertical": vertical,
        "titulo": titulo,
        "slug": slug,
        "tipo_artigo": tipo_artigo,
        "meta_description": meta,
        "outlines": outlines,
        "markdown": f"{THEME_SCRIPTS_BLOG}/{vertical}/{stem}.md",
        "capa": capa_rel,
        "taxonomias": taxonomias,
    }


def interleave_by_vertical(items: list[dict], seed: int = SEED) -> list[dict]:
    """Round-robin entre verticais (pasta), com shuffle estável dentro de cada uma."""
    rng = random.Random(seed)
    groups: dict[str, list[dict]] = defaultdict(list)
    for it in items:
        groups[it["vertical"]].append(it)

    for v in groups:
        rng.shuffle(groups[v])

    keys = sorted(groups.keys(), key=lambda k: (-len(groups[k]), k))
    queues = {k: deque(groups[k]) for k in keys}
    out: list[dict] = []
    while any(queues[k] for k in queues):
        for k in keys:
            if queues[k]:
                out.append(queues[k].popleft())
    return out


def collect_all_items() -> list[dict]:
    items: list[dict] = []
    for json_path in sorted(BLOG_ROOT.glob("*/artigo_*.json")):
        if json_path.name.startswith("."):
            continue
        try:
            data = json.loads(json_path.read_text(encoding="utf-8"))
        except (json.JSONDecodeError, OSError) as e:
            print(f"[AVISO] JSON inválido ou ilegível: {json_path} ({e})")
            continue

        vertical = json_path.parent.name
        stem = json_path.stem
        raw_list = flatten_json_articles(data, json_path)

        if len(raw_list) > 1:
            print(
                f"[AVISO] {json_path.relative_to(BLOG_ROOT)} tem {len(raw_list)} artigos; "
                "só o manifesto por ficheiro homónimo .md suporta 1 artigo — usando o primeiro."
            )

        if not raw_list:
            continue

        raw = raw_list[0]
        item = build_manifest_item(json_path, raw, stem, vertical)
        if not item:
            print(f"[AVISO] Sem .md para {json_path.relative_to(BLOG_ROOT)}, ignorado.")
            continue
        if not item.get("capa"):
            print(f"[AVISO] Sem capa resolvida para {item['id']}")

        items.append(item)
    return items


def main() -> None:
    manifest_path = BLOG_ROOT / MANIFEST_NAME
    backup_path = BLOG_ROOT / (MANIFEST_NAME.replace(".json", "") + BACKUP_SUFFIX)

    items = collect_all_items()
    ordered = interleave_by_vertical(items)

    if manifest_path.is_file():
        backup_path.write_text(manifest_path.read_text(encoding="utf-8"), encoding="utf-8")
        print(f"Backup: {backup_path.name}")

    payload = {
        "versao": 2,
        "gerado_em": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "descricao": "Fila editorial: ordem sugere datas de publicação (primeiro = mais antigo). "
        "Verticais intercaladas para evitar sequências do mesmo tema. "
        "Cada item inclui 'outlines' (subtítulos): JSON MANUS outline 1..N ou, se vazio, cabeçalhos ## do .md.",
        "total": len(ordered),
        "itens": ordered,
    }

    manifest_path.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    print(f"Escrito {MANIFEST_NAME} com {len(ordered)} artigos.")


if __name__ == "__main__":
    main()

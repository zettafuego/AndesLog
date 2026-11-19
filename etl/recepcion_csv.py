"""ETL de ASN. Lee el CSV en flujo, lo parte por ASN en disco y lo envía al API.

No carga el archivo en una lista. Cada fila se escribe a un spool y se olvida.
Sirve para un CSV grande sin inflar la RAM del puesto que corre el turno.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import shutil
import sys
import urllib.error
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parent
SPOOL = ROOT / "spool"


def rows(path: Path):
    with path.open(newline="", encoding="utf-8-sig") as handle:
        reader = csv.DictReader(handle)
        expected = {"asn", "supplier", "barcode", "qty", "eta"}
        header = {name.strip() for name in (reader.fieldnames or [])}
        missing = expected - header
        if missing:
            raise SystemExit(f"Faltan columnas: {', '.join(sorted(missing))}")
        for line_no, raw in enumerate(reader, start=2):
            item = {key.strip(): (value or "").strip() for key, value in raw.items() if key}
            if not item.get("asn"):
                print(f"línea {line_no}: ASN vacío, se omite", file=sys.stderr)
                continue
            yield item


def spool_by_asn(path: Path) -> list[Path]:
    if SPOOL.exists():
        shutil.rmtree(SPOOL)
    SPOOL.mkdir(parents=True)
    handles: dict[str, tuple] = {}
    files: list[Path] = []
    try:
        for item in rows(path):
            asn = item["asn"]
            if asn not in handles:
                target = SPOOL / f"{safe_name(asn)}.csv"
                handle = target.open("w", newline="", encoding="utf-8")
                writer = csv.DictWriter(handle, fieldnames=["asn", "supplier", "barcode", "qty", "eta"])
                writer.writeheader()
                handles[asn] = (handle, writer, target)
                files.append(target)
            handles[asn][1].writerow({
                "asn": item["asn"],
                "supplier": item["supplier"],
                "barcode": item["barcode"],
                "qty": item["qty"],
                "eta": item["eta"],
            })
    finally:
        for handle, _writer, _target in handles.values():
            handle.close()
    return files


def safe_name(value: str) -> str:
    cleaned = "".join(ch if ch.isalnum() or ch in "-_" else "_" for ch in value)
    return cleaned[:80] or "asn"


def login(api: str, code: str, pin: str) -> str:
    payload = json.dumps({"code": code, "pin": pin}).encode()
    request = urllib.request.Request(
        f"{api}/auth/login",
        data=payload,
        headers={"Accept": "application/json", "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        body = json.loads(response.read().decode())
    return body["token"]


def post_file(api: str, token: str, path: Path) -> dict:
    boundary = "----andeslogetl"
    content = path.read_bytes()
    digest = hashlib.sha256(content).hexdigest()
    preamble = (
        f"--{boundary}\r\n"
        f'Content-Disposition: form-data; name="file"; filename="{path.name}"\r\n'
        "Content-Type: text/csv\r\n\r\n"
    ).encode()
    ending = f"\r\n--{boundary}--\r\n".encode()
    body = preamble + content + ending
    request = urllib.request.Request(
        f"{api}/etl/recepciones",
        data=body,
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {token}",
            "Content-Type": f"multipart/form-data; boundary={boundary}",
            "Idempotency-Key": digest,
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            payload = json.loads(response.read().decode())
            payload["replay"] = response.headers.get("Idempotent-Replay") == "true"
            return payload
    except urllib.error.HTTPError as error:
        detail = error.read().decode(errors="replace")
        raise SystemExit(f"{path.name}: HTTP {error.code} {detail}") from error


def main() -> None:
    parser = argparse.ArgumentParser(description="Carga ASN de recepción a AndesLog")
    parser.add_argument("--csv", required=True, type=Path)
    parser.add_argument("--api", default="http://127.0.0.1:8000/api")
    parser.add_argument("--code", default="OP-LIMA-01")
    parser.add_argument("--pin", default="1847")
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    files = spool_by_asn(args.csv)
    print(f"spool: {len(files)} ASN en {SPOOL}")
    if args.dry_run:
        for path in files:
            print(path.name)
        return

    token = login(args.api.rstrip("/"), args.code, args.pin)
    for path in files:
        result = post_file(args.api.rstrip("/"), token, path)
        replay = " replay" if result.get("replay") else ""
        print(
            f"{path.name}: creados={result.get('created')} lineas={result.get('lines')} "
            f"omitidos={result.get('skipped')}{replay}"
        )


if __name__ == "__main__":
    main()

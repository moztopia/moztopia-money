"""Build a reviewed factual ISO snapshot from the SIX List One XML file."""

import argparse
import hashlib
import json
from pathlib import Path
from xml.etree import ElementTree


def main() -> None:
    """Read a supplied XML file and regenerate deterministic bundled metadata."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source", type=Path, help="Downloaded official List One XML")
    args = parser.parse_args()
    raw = args.source.read_bytes()
    root = ElementTree.fromstring(raw)
    currencies = {}
    undefined = set()
    symbols = {
        "USD": "$", "EUR": "€", "GBP": "£", "JPY": "¥", "CNY": "¥",
        "THB": "฿", "INR": "₹", "KRW": "₩", "VND": "₫", "PHP": "₱",
        "IDR": "Rp", "MYR": "RM", "RUB": "₽", "TRY": "₺", "ILS": "₪",
    }
    for entry in root.findall("./CcyTbl/CcyNtry"):
        code = entry.findtext("Ccy")
        scale = entry.findtext("CcyMnrUnts", "")
        if not code:
            continue
        if not scale.isdigit():
            undefined.add(code)
            continue
        definition = {
            "name": entry.findtext("CcyNm"),
            "minorUnits": int(scale),
            "symbol": symbols.get(code, code),
        }
        if code in currencies and currencies[code] != definition:
            raise ValueError(f"Conflicting ISO rows for {code}")
        currencies[code] = definition
    if len(currencies) < 150:
        raise ValueError("Unexpectedly incomplete ISO currency list")
    snapshot = {
        "source": "https://www.six-group.com/dam/download/financial-information/"
        "data-center/iso-currrency/lists/list-one.xml",
        "published": root.attrib["Pblshd"],
        "sha256": hashlib.sha256(raw).hexdigest(),
        "undefinedMinorUnits": sorted(undefined),
        "currencies": dict(sorted(currencies.items())),
    }
    destination = Path(__file__).resolve().parents[1] / "resources/currencies.json"
    destination.write_text(
        json.dumps(snapshot, indent=4, ensure_ascii=False) + "\n", encoding="utf-8"
    )
    print(f"Wrote {len(currencies)} currency definitions from {snapshot['published']}.")


if __name__ == "__main__":
    main()

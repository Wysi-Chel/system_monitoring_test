import json
import sys
import zipfile
from pathlib import Path


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: export_memo_docx_helper.py <payload.json>", file=sys.stderr)
        return 2

    payload_path = Path(sys.argv[1])
    with payload_path.open("r", encoding="utf-8-sig") as handle:
        payload = json.load(handle)

    output_path = Path(payload["output"])
    files = payload["files"]

    with zipfile.ZipFile(output_path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for archive_name, content in files.items():
            archive.writestr(archive_name, content)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

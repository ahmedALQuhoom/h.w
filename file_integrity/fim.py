# ==========================================
# Tool 9: File Integrity Monitor (FIM)
# Created by: Ahmed_Q
# ==========================================

import argparse
import hashlib
import time
from pathlib import Path


def calculate_sha256(file_path: Path, chunk_size: int = 1024 * 1024) -> str:
    """Calculate SHA-256 hash of a file by reading it in chunks to minimize memory usage."""
    hasher = hashlib.sha256()

    with file_path.open("rb") as file:
        while chunk := file.read(chunk_size):
            hasher.update(chunk)

    return hasher.hexdigest()


def save_baseline(baseline_path: Path, file_hash: str) -> None:
    """Save the baseline hash into a text file."""
    baseline_path.write_text(file_hash + "\n", encoding="ascii")


def load_baseline(baseline_path: Path) -> str | None:
    """Read the baseline hash if the file exists."""
    if not baseline_path.exists():
        return None

    file_hash = baseline_path.read_text(encoding="ascii").strip()

    # Validate that the file contains a valid SHA-256 hash.
    if len(file_hash) != 64 or any(c not in "0123456789abcdef" for c in file_hash.lower()):
        raise ValueError(f"Invalid baseline hash file: {baseline_path}")

    return file_hash.lower()


def monitor_file(file_path: Path, baseline_path: Path, interval: int) -> None:
    if not file_path.is_file():
        raise FileNotFoundError(f"Target file to monitor does not exist: {file_path}")

    if file_path.resolve() == baseline_path.resolve():
        raise ValueError("The baseline file must be different from the monitored file.")

    baseline_hash = load_baseline(baseline_path)

    if baseline_hash is None:
        baseline_hash = calculate_sha256(file_path)
        save_baseline(baseline_path, baseline_hash)
        print(f"[INFO] Baseline hash created: {baseline_path}")
        print(f"[INFO] SHA-256: {baseline_hash}")
    else:
        print(f"[INFO] Baseline hash loaded from: {baseline_path}")
        print(f"[INFO] Reference SHA-256: {baseline_hash}")

    print(f"[MONITOR] File: {file_path}")
    print(f"[MONITOR] Check interval: {interval} seconds. Press Ctrl+C to stop.")

    alert_active = False
    missing_alert_active = False

    while True:
        try:
            current_hash = calculate_sha256(file_path)

            if current_hash != baseline_hash:
                if not alert_active:
                    print(
                        "\n[CRITICAL SECURITY ALERT] File content change detected!"
                        f"\nPath: {file_path}"
                        f"\nBaseline Hash: {baseline_hash}"
                        f"\nCurrent Hash:  {current_hash}"
                    )
                    alert_active = True
            else:
                if alert_active:
                    print("\n[INFO] File hash has returned to the baseline.")
                alert_active = False

            missing_alert_active = False

        except FileNotFoundError:
            if not missing_alert_active:
                print(f"\n[CRITICAL SECURITY ALERT] File not found: {file_path}")
                missing_alert_active = True

        except PermissionError:
            print(f"\n[ERROR] Permission denied to read file: {file_path}")

        except OSError as error:
            print(f"\n[ERROR] Failed to read file: {error}")

        time.sleep(interval)


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Monitor file integrity using SHA-256 hashing."
    )
    parser.add_argument("file", help="Path of the file to monitor.")
    parser.add_argument(
        "--interval",
        type=int,
        default=3,
        help="Scanning interval in seconds (default: 3).",
    )
    parser.add_argument(
        "--baseline",
        help="Path to the baseline hash file; default appends .sha256 to the file name.",
    )
    args = parser.parse_args()

    if args.interval < 1:
        parser.error("The interval must be at least 1 second.")

    file_path = Path(args.file)
    baseline_path = (
        Path(args.baseline)
        if args.baseline
        else Path(str(file_path) + ".sha256")
    )

    try:
        monitor_file(file_path, baseline_path, args.interval)
    except (FileNotFoundError, PermissionError, ValueError, OSError) as error:
        print(f"[ERROR] {error}")
    except KeyboardInterrupt:
        print("\n[INFO] Monitoring stopped by user.")

    # Print footer signature
    print("\n----------------------------------------")
    print("Tool 9 | Created by: Ahmed_Q")
    print("----------------------------------------")


if __name__ == "__main__":
    main()
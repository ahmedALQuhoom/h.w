# ==========================================
# Tool 5: Multithreaded Port Scanner
# Created by: Ahmed_Q
# ==========================================

import argparse
import socket
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed


def check_port(target: str, port: int, timeout: float) -> tuple[int, bool]:
    """Check if a specific TCP port is open."""
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.settimeout(timeout)
            result = sock.connect_ex((target, port))
            return port, result == 0
    except OSError:
        return port, False


def parse_ports(value: str) -> set[int]:
    """Parse comma-separated port numbers or ranges (e.g., 1-1024 or 22,80,443)."""
    ports = set()
    parts = value.split(",")
    for part in parts:
        part = part.strip()
        if "-" in part:
            try:
                start_str, end_str = part.split("-")
                start, end = int(start_str), int(end_str)
                if start > end or start < 1 or end > 65535:
                    raise ValueError
                ports.update(range(start, end + 1))
            except ValueError:
                raise argparse.ArgumentTypeError(
                    f"Invalid port range: '{part}'. Use format like 1-1024."
                )
        else:
            try:
                port = int(part)
                if port < 1 or port > 65535:
                    raise ValueError
                ports.add(port)
            except ValueError:
                raise argparse.ArgumentTypeError(
                    f"Invalid port number: '{part}'. Must be between 1 and 65535."
                )
    return ports


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Multithreaded TCP Port Scanner for active service discovery."
    )
    parser.add_argument("target", help="Target IP address or hostname.")
    parser.add_argument(
        "--ports",
        type=parse_ports,
        default=set(range(1, 1025)),
        help="Ports to scan (e.g., 1-1024 or 22,80,443). Default: 1-1024.",
    )
    parser.add_argument(
        "--workers",
        type=int,
        default=50,
        help="Number of concurrent threads (default: 50).",
    )
    parser.add_argument(
        "--timeout",
        type=float,
        default=1.0,
        help="Connection timeout in seconds (default: 1.0).",
    )

    args = parser.parse_args()

    if args.timeout <= 0:
        parser.error("Timeout must be greater than zero.")
    if args.workers < 1:
        parser.error("Number of workers must be at least 1.")

    print(f"[*] Starting port scan on target: {args.target}")
    print(f"[*] Total ports to scan: {len(args.ports)}")
    print(f"[*] Using {args.workers} concurrent threads with timeout {args.timeout}s...\n")

    start_time = time.monotonic()
    open_ports = []

    with ThreadPoolExecutor(max_workers=args.workers) as executor:
        futures = {
            executor.submit(check_port, args.target, port, args.timeout): port
            for port in args.ports
        }
        for future in as_completed(futures):
            port, is_open = future.result()
            if is_open:
                open_ports.append(port)
                print(f"[+] Port {port} is OPEN", flush=True)

    duration = time.monotonic() - start_time

    print("\n--- Scan Summary ---")
    print(f"Target      : {args.target}")
    print(f"Open Ports  : {sorted(open_ports) if open_ports else 'None'}")
    print(f"Time Taken  : {duration:.2f} seconds")
    
    print("\n----------------------------------------")
    print("Tool 5 | Created by: Ahmed_Q")
    print("----------------------------------------")


if __name__ == "__main__":
    main()
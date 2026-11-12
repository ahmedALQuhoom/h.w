# ==========================================
# Tool 6: Service Banner Grabber
# Created by: Ahmed_Q
# ==========================================

import argparse
import socket
import ssl

DEFAULT_HTTP_PORTS = {80, 8000, 8080, 8888, 5000, 3000}
DEFAULT_HTTPS_PORTS = {443, 8443}


def parse_ports(value: str) -> set[int]:
    """Parse a comma-separated list of port numbers (e.g., 22,80,443)."""
    try:
        ports = {int(item.strip()) for item in value.split(",")}
    except ValueError:
        raise argparse.ArgumentTypeError(
            "Please enter comma-separated port numbers (e.g., 22,80,443)."
        )
    
    if not ports or any(port < 1 or port > 65535 for port in ports):
        raise argparse.ArgumentTypeError(
            "Port numbers must be between 1 and 65535."
        )
    return ports


def receive_http_response(sock: socket.socket, limit: int = 1024) -> bytes:
    """Read HTTP response up to the headers end or max limit."""
    data = bytearray()
    while len(data) < limit:
        try:
            chunk = sock.recv(min(256, limit - len(data)))
        except socket.timeout:
            break
        if not chunk:
            break
        data.extend(chunk)
        if b"\r\n\r\n" in data:
            break
    return bytes(data)


def grab_banner(
    target: str,
    port: int,
    timeout: float,
    http_ports: set[int],
    https_ports: set[int],
) -> str:
    """Connects to a port, handles HTTP/HTTPS or raw banner grabbing."""
    is_https = port in https_ports
    is_http = port in http_ports or is_https

    try:
        with socket.create_connection((target, port), timeout=timeout) as raw_sock:
            raw_sock.settimeout(timeout)

            if is_https:
                context = ssl.create_default_context()
                context.check_hostname = False
                context.verify_mode = ssl.CERT_NONE
                sock = context.wrap_socket(raw_sock, server_hostname=target)
            else:
                sock = raw_sock

            if is_http:
                request = (
                    f"HEAD / HTTP/1.1\r\n"
                    f"Host: {target}\r\n"
                    f"User-Agent: Local-Banner-Check/1.0\r\n"
                    f"Connection: close\r\n\r\n"
                )
                sock.sendall(request.encode("ascii", errors="ignore"))
                response = receive_http_response(sock)
            else:
                try:
                    response = sock.recv(1024)
                except socket.timeout:
                    response = b""

            if not response:
                return "Connection successful, but no banner received."
            
            return response.decode("utf-8", errors="ignore").strip()

    except socket.timeout:
        return "Connection or banner reading timeout."
    except ConnectionRefusedError:
        return "Connection refused; port may be closed."
    except ssl.SSLError as exc:
        return f"TLS handshake failed: {exc}"
    except OSError as exc:
        return f"Connection failed: {exc}"


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Collect initial service banners on specified TCP ports."
    )
    parser.add_argument("target", help="Target IP address or hostname.")
    parser.add_argument(
        "--ports",
        type=parse_ports,
        default=parse_ports("21,22,25,80,110,143,443,8080,8443"),
        help="Comma-separated ports (default: 21,22,25,80,110,143,443,8080,8443).",
    )
    parser.add_argument(
        "--timeout",
        type=float,
        default=2.0,
        help="Connection and banner read timeout in seconds (default: 2.0).",
    )
    parser.add_argument(
        "--http-ports",
        type=parse_ports,
        default=DEFAULT_HTTP_PORTS,
        help="Ports to send an HTTP HEAD request.",
    )
    parser.add_argument(
        "--https-ports",
        type=parse_ports,
        default=DEFAULT_HTTPS_PORTS,
        help="Ports to send an HTTPS HEAD request over TLS.",
    )

    args = parser.parse_args()

    if args.timeout <= 0:
        parser.error("Timeout must be greater than zero.")
    if "\r" in args.target or "\n" in args.target:
        parser.error("Invalid target name.")

    ports_str = ", ".join(map(str, sorted(args.ports)))
    print(f"Collecting banners from {args.target} for ports: {ports_str}")

    for port in sorted(args.ports):
        result = grab_banner(
            args.target,
            port,
            args.timeout,
            args.http_ports,
            args.https_ports,
        )

        lines = [line.strip() for line in result.splitlines() if line.strip()]
        print(f"\n--- TCP/{port} ---")
        if not lines:
            print("No data received.")
            continue
        
        print(f"Banner: {lines[0]}")
        for line in lines[1:]:
            print(f"        {line}")

    print("\n----------------------------------------")
    print("Tool 6 | Created by: Ahmed_Q")
    print("----------------------------------------")


if __name__ == "__main__":
    main()
# ==========================================
# Tool 8: Password Generator & SHA-256 Hasher
# Created by: Ahmed_Q
# ==========================================

import argparse
import hashlib
import secrets
import string


def generate_password(length: int = 16) -> str:
    """Generate a secure random password ensuring at least one character from each category."""
    if length < 4:
        raise ValueError("Password length must be at least 4 characters.")
    
    categories = [
        string.ascii_lowercase,
        string.ascii_uppercase,
        string.digits,
        string.punctuation,
    ]
    
    # Pick at least one character from each category
    password_chars = [secrets.choice(category) for category in categories]
    
    # Fill the rest of the length from the full character set
    alphabet = string.ascii_letters + string.digits + string.punctuation
    password_chars.extend(
        secrets.choice(alphabet) for _ in range(length - len(password_chars))
    )
    
    # Shuffle characters so categories don't appear in a fixed order
    secrets.SystemRandom().shuffle(password_chars)
    
    return "".join(password_chars)


def sha256_hash(text: str) -> str:
    """Return the SHA-256 hexadecimal hash of a given text."""
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Generate a secure random password and calculate its SHA-256 hash."
    )
    parser.add_argument(
        "--length",
        type=int,
        default=16,
        help="Password length (default: 16, minimum: 4).",
    )
    
    args = parser.parse_args()

    if args.length < 4:
        parser.error("Password length must be at least 4 characters.")

    password = generate_password(args.length)
    password_hash = sha256_hash(password)

    print(f"Generated Password : {password}")
    print(f"SHA-256 Hash       : {password_hash}")
    
    # Print at the end of the results
    print("\n----------------------------------------")
    print("Tool 8 | Created by: Ahmed_Q")
    print("----------------------------------------")


if __name__ == "__main__":
    main()
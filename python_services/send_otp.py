import os
import sys
import json
import urllib.request
import urllib.error

def send_otp(recipient_email, otp_code):
    api_key = os.environ.get("BREVO_API_KEY", "").strip()
    sender_email = os.environ.get("SMTP_EMAIL", "piyushkumawat911@gmail.com").strip()

    if not api_key:
        print("ERROR: BREVO_API_KEY not configured")
        sys.exit(1)

    url = "https://api.brevo.com/v3/smtp/email"
    payload = {
        "sender": {"name": "VMS Security Portal", "email": sender_email},
        "to": [{"email": recipient_email}],
        "subject": f"{otp_code} is your VMS Portal Password Reset Code",
        "htmlContent": f"""
        <div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 480px; margin: auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 10px;">
            <h2 style="color: #111827; margin-top: 0;">Password Reset Request</h2>
            <p style="color: #475569; font-size: 15px;">Use the one-time verification code below to reset your VMS Portal password. This code expires in <strong>10 minutes</strong>.</p>
            <div style="background: #f1f5f9; padding: 16px; text-align: center; border-radius: 8px; margin: 20px 0;">
                <span style="font-size: 28px; font-weight: 700; letter-spacing: 6px; color: #0f766e;">{otp_code}</span>
            </div>
            <p style="color: #94a3b8; font-size: 12px; margin-bottom: 0;">If you did not request this reset, you can safely ignore this email.</p>
        </div>
        """
    }

    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "accept": "application/json",
            "api-key": api_key,
            "content-type": "application/json"
        },
        method="POST"
    )

    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            if resp.status in (200, 201, 202):
                print("SUCCESS")
            else:
                print(f"ERROR: HTTP {resp.status}")
                sys.exit(1)
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8", errors="ignore")
        print(f"ERROR: HTTP {e.code} - {err_body}")
        sys.exit(1)
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("ERROR: Missing arguments")
        sys.exit(1)
    send_otp(sys.argv[1], sys.argv[2])
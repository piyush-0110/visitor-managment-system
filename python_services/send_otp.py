import os
import sys
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

def send_otp(recipient_email, otp_code):
    sender_email = os.environ.get("SMTP_EMAIL", "yourpersonalmail@gmail.com")
    sender_password = os.environ.get("SMTP_PASSWORD", "")

    if not sender_password:
        print("ERROR: SMTP_PASSWORD not configured")
        sys.exit(1)

    msg = MIMEMultipart("alternative")
    msg["Subject"] = f"{otp_code} is your VMS Portal Password Reset Code"
    msg["From"] = f"VMS Security Portal <{sender_email}>"
    msg["To"] = recipient_email

    html = f"""
    <div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 480px; margin: auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 10px;">
        <h2 style="color: #0f172a; margin-top: 0;">Password Reset Request</h2>
        <p style="color: #475569; font-size: 15px;">Use the one-time verification code below to reset your VMS Portal password. This code expires in <strong>10 minutes</strong>.</p>
        <div style="background: #f1f5f9; padding: 16px; text-align: center; border-radius: 8px; margin: 20px 0;">
            <span style="font-size: 28px; font-weight: 700; letter-spacing: 6px; color: #2563eb;">{otp_code}</span>
        </div>
        <p style="color: #94a3b8; font-size: 12px; margin-bottom: 0;">If you did not request this reset, you can safely ignore this email.</p>
    </div>
    """
    msg.attach(MIMEText(html, "html"))

    try:
        with smtplib.SMTP_SSL("smtp.gmail.com", 465) as server:
            server.login(sender_email, sender_password)
            server.sendmail(sender_email, recipient_email, msg.as_string())
        print("SUCCESS")
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("ERROR: Missing arguments")
        sys.exit(1)
    send_otp(sys.argv[1], sys.argv[2])
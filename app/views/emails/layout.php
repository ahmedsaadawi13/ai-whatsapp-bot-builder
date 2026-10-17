<?php // FILE: /app/views/emails/layout.php ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title><?php echo e($subject); ?></title></head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
    <div style="background:#128c7e;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:bold;">AI WhatsApp Bot Builder</div>
    <div style="padding:24px;line-height:1.5;"><?php echo $content; ?></div>
    <div style="padding:12px 24px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;">
      This is an automated message from AI WhatsApp Bot Builder.
    </div>
  </div>
</body>
</html>

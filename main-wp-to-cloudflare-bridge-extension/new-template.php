<?php
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Website Maintenance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #2D3B44;
            line-height: 1.6;
        }
        .content {
            max-width: 600px;
            margin: 0 auto;
            padding: 2em;
        }
        .icon {
            font-size: 1.5em;
            vertical-align: middle;
        }
        .highlight {
            font-size: 1.2em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="content">
        <p>Hey [First Name]!</p>

        <p>Congratulations on another successful month online!</p>

        <p>Here’s an overview of just some of the work we carried out on your website this month:</p>

        <p><span class="icon">💾</span> <span class="highlight">[backup.created.count] Off-site backups</span> have been made for security and data retention.</p>

        <p><span class="icon">🆕</span> <span class="highlight">[website.updated.total] Plugin, theme, and/or WordPress core updates</span> have been performed to boost security and enhance features.</p>

        <p><span class="icon">🦠</span> <span class="highlight">[ithemes.scan.count] Vulnerability scans</span> to ensure your website is free from any viruses or malware.</p>

        <p><span class="icon">🛑</span> <span class="highlight">[ithemes.blocked.count] Threats blocked</span> from gaining unauthorized access.</p>

        <p>[optional details or CTA here]</p>

        <p>Let me know if I can do anything else to be helpful! <span class="icon">🔔</span></p>

        <p>All the best,</p>

        <p>Your Name</p>

        <p><a href="https://yourcompany.com">Your Company</a> | <a href="mailto:anybody@yourcompany.com">anybody@yourcompany.com</a></p>
    </div>
</body>
</html>

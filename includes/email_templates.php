
<?php
/**
 * Generate HTML email for admin registration code
 */
function getAdminCodeEmailTemplate($code) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; padding: 20px 0; }
            .code { background-color: #f4f4f4; padding: 15px; font-size: 24px; text-align: center; 
                    letter-spacing: 5px; margin: 20px 0; font-weight: bold; }
            .footer { font-size: 12px; color: #999; margin-top: 30px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Axiom Admin Registration</h2>
            </div>
            <p>Hello,</p>
            <p>Your admin registration code for Axiom Shop is:</p>
            <div class="code">' . $code . '</div>
            <p>This code will expire in 24 hours.</p>
            <p>If you didn\'t request this code, please ignore this email.</p>
            <p>Best regards,<br>Axiom Shop Team</p>
            <div class="footer">
                This is an automated message, please do not reply.
            </div>
        </div>
    </body>
    </html>';
}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status Update - FoodFlow</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .header {
            background: #EF4444;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
        }
        .content h2 {
            color: #333;
            margin-top: 0;
        }
        .content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .reason-box {
            background: #FEF2F2;
            border-left: 4px solid #EF4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .reason-box p {
            margin: 0;
            color: #991B1B;
        }
        .button {
            display: inline-block;
            background: #EF4444;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            margin-top: 20px;
        }
        .footer {
            background: #F8F8F8;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Application Status Update</h1>
        </div>
        <div class="content">
            <h2>Application Decision</h2>
            <p>Dear {{ $application->partner_type == 'restaurant' ? $application->contact_name : $application->full_name }},</p>
            <p>Thank you for your interest in partnering with <strong>FoodFlow</strong>. After careful review of your application, we regret to inform you that we are unable to approve your application at this time.</p>
            
            <div class="reason-box">
                <strong>📝 Reason for rejection:</strong>
                <p>{{ $reason }}</p>
            </div>
            
            <p>Common reasons for rejection include:</p>
            <ul>
                <li>Incomplete or inaccurate information provided</li>
                <li>Missing required documents</li>
                <li>Documents not meeting our quality standards</li>
                <li>Business not operating in our service areas</li>
            </ul>
            
            <p>If you believe this decision was made in error or would like to reapply after addressing the above issues, please contact our support team at <strong>support@foodflow.com</strong>.</p>
            
            <p>We wish you the best in your future endeavors.</p>
            <p>Best regards,<br><strong>The FoodFlow Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} FoodFlow. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

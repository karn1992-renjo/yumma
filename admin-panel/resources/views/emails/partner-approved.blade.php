<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Approved - FoodFlow</title>
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
            background: linear-gradient(135deg, #EF4F5F, #FF8C42);
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
        .badge {
            display: inline-block;
            background: #10B981;
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .info-box {
            background: #F8F8F8;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-box strong {
            color: #333;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #EF4F5F, #FF8C42);
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
        @media (max-width: 600px) {
            .container { width: 100%; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Congratulations!</h1>
        </div>
        <div class="content">
            @if($type == 'restaurant')
                <div class="badge">Restaurant Partner</div>
            @else
                <div class="badge">Delivery Partner</div>
            @endif
            
            <h2>Your Application has been Approved!</h2>
            <p>Dear {{ $application->partner_type == 'restaurant' ? $application->contact_name : $application->full_name }},</p>
            <p>We are pleased to inform you that your application to partner with <strong>FoodFlow</strong> has been <strong>approved</strong>! Welcome to the FoodFlow family.</p>
            
            <div class="info-box">
                <strong>📋 Account Details:</strong><br><br>
                <strong>Email:</strong> {{ $email }}<br>
                <strong>Password:</strong> {{ $password }}<br><br>
                <strong>🔐 Important:</strong> Please change your password after your first login for security.
            </div>
            
            <p>You can now login to your dashboard and start using our platform:</p>
            <p style="text-align: center;">
                <a href="{{ url('/login') }}" class="button">Login to Your Account →</a>
            </p>
            
            @if($type == 'restaurant')
                <p><strong>Next Steps for Restaurant Partner:</strong></p>
                <ul>
                    <li>Complete your restaurant profile with images and details</li>
                    <li>Add your menu items with prices and descriptions</li>
                    <li>Set your operating hours and delivery zones</li>
                    <li>Start accepting orders from customers!</li>
                </ul>
            @else
                <p><strong>Next Steps for Delivery Partner:</strong></p>
                <ul>
                    <li>Complete your profile and upload profile picture</li>
                    <li>Download the FoodFlow Delivery Partner App</li>
                    <li>Set your availability and start accepting delivery requests</li>
                    <li>Track your earnings and get paid weekly</li>
                </ul>
            @endif
            
            <p>If you have any questions or need assistance, please don't hesitate to contact our support team at <strong>support@foodflow.com</strong>.</p>
            <p>Best regards,<br><strong>The FoodFlow Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} FoodFlow. All rights reserved.</p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>

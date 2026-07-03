<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Received - FoodFlow</title>
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
        .success-box {
            background: #ECFDF5;
            border-left: 4px solid #10B981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .info-box {
            background: #F8F8F8;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .info-box strong {
            font-size: 24px;
            color: #EF4F5F;
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
            <h1>✅ Application Received!</h1>
        </div>
        <div class="content">
            <div class="success-box">
                Thank you for applying to partner with <strong>FoodFlow</strong>!
            </div>
            
            <h2>Dear {{ $application->partner_type == 'restaurant' ? $application->contact_name : $application->full_name }},</h2>
            <p>We have successfully received your application to become a <strong>{{ ucfirst($application->partner_type) }} Partner</strong> on the FoodFlow platform.</p>
            
            <div class="info-box">
                <strong>{{ $application->application_number }}</strong><br>
                <span style="font-size: 12px; color: #666;">Your Application Reference Number</span>
            </div>
            
            <p><strong>What happens next?</strong></p>
            <ol>
                <li>Our team will review your application within 2-3 business days</li>
                <li>We may contact you for any additional information if needed</li>
                <li>Once approved, you will receive login credentials via email</li>
                <li>You can then start using the FoodFlow platform</li>
            </ol>
            
            <p><strong>Need assistance?</strong> If you have any questions or need to update your application, please contact our support team at <strong>support@foodflow.com</strong> or call us at <strong>+91 1800 123 4567</strong>.</p>
            
            <p>Thank you for choosing FoodFlow! We look forward to growing together.</p>
            <p>Best regards,<br><strong>The FoodFlow Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} FoodFlow. All rights reserved.</p>
            <p>This is a system-generated email, please do not reply.</p>
        </div>
    </div>
</body>
</html>

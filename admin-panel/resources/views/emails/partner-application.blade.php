<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Partner Application - FoodFlow</title>
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
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .content h2 {
            color: #333;
            margin-top: 0;
        }
        .alert {
            background: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #E8E8E8;
        }
        .info-table td:first-child {
            font-weight: 600;
            width: 40%;
            color: #333;
        }
        .badge {
            display: inline-block;
            background: #EF4F5F;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 New Partner Application</h1>
        </div>
        <div class="content">
            <div class="alert">
                A new partner application has been submitted and requires your review.
            </div>
            
            <h2>Application Details</h2>
            <table class="info-table">
                <tr>
                    <td>Application Number:</td>
                    <td><strong>{{ $application->application_number }}</strong></td>
                </tr>
                <tr>
                    <td>Partner Type:</td>
                    <td><span class="badge">{{ ucfirst($application->partner_type) }}</span></td>
                </tr>
                <tr>
                    <td>Submitted On:</td>
                    <td>{{ $application->created_at->format('F d, Y \a\t h:i A') }}</td>
                </tr>
            </table>
            
            @if($application->partner_type == 'restaurant')
                <h3>🏪 Restaurant Information</h3>
                <table class="info-table">
                    <tr><td>Business Name:</td><td>{{ $application->business_name }}</td></tr>
                    <tr><td>Business Email:</td><td>{{ $application->business_email }}</td></tr>
                    <tr><td>Business Phone:</td><td>{{ $application->business_phone }}</td></tr>
                    <tr><td>City:</td><td>{{ $application->city }}</td></tr>
                    <tr><td>Address:</td><td>{{ $application->address }}</td></tr>
                </table>
                
                <h3>👤 Contact Person</h3>
                <table class="info-table">
                    <tr><td>Name:</td><td>{{ $application->contact_name }}</td></tr>
                    <tr><td>Designation:</td><td>{{ $application->contact_designation }}</td></tr>
                    <tr><td>Email:</td><td>{{ $application->contact_email }}</td></tr>
                    <tr><td>Phone:</td><td>{{ $application->contact_phone }}</td></tr>
                </table>
            @else
                <h3>🛵 Driver Information</h3>
                <table class="info-table">
                    <tr><td>Full Name:</td><td>{{ $application->full_name }}</td></tr>
                    <tr><td>Email:</td><td>{{ $application->email }}</td></tr>
                    <tr><td>Phone:</td><td>{{ $application->phone }}</td></tr>
                    <tr><td>City:</td><td>{{ $application->city }}</td></tr>
                    <tr><td>Vehicle Type:</td><td>{{ ucfirst($application->vehicle_type) }}</td></tr>
                    <tr><td>Vehicle Number:</td><td>{{ $application->vehicle_number }}</td></tr>
                    <tr><td>License Number:</td><td>{{ $application->license_number }}</td></tr>
                </table>
            @endif
            
            <p style="text-align: center;">
                <a href="{{ url('/admin/partner-applications/' . $application->id) }}" class="button">Review Application →</a>
            </p>
            
            <p>Please login to the admin panel to review and process this application.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} FoodFlow. All rights reserved.</p>
            <p>This is an automated notification from FoodFlow Partner Portal.</p>
        </div>
    </div>
</body>
</html>

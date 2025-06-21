<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Generation Failed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #dc2626;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #fef2f2;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .error-box {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Report Generation Failed</h1>
        <p>We encountered an issue generating your analytics report</p>
    </div>
    
    <div class="content">
        <h2>Hello {{ $employer->first_name }},</h2>
        
        <p>We're sorry to inform you that there was an issue generating your analytics report.</p>
        
        <div class="error-box">
            <h3>Error Details:</h3>
            <p><strong>Error:</strong> {{ $error }}</p>
            <p><strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
        
        <p>Our technical team has been automatically notified of this issue and will work to resolve it as soon as possible.</p>
        
        <h3>What you can do:</h3>
        <ul>
            <li>Try generating the report again in a few minutes</li>
            <li>Check if you have sufficient data for the selected time period</li>
            <li>Contact our support team if the issue persists</li>
        </ul>
        
        <p>
            <a href="{{ config('app.frontend_url') }}/dashboard/employer/analytics-plus" class="button">
                Try Again
            </a>
        </p>
        
        <p>We apologize for the inconvenience and appreciate your patience.</p>
        
        <p>Best regards,<br>
        The ApplyMandu Support Team</p>
    </div>
    
    <div class="footer">
        <p>If you continue to experience issues, please contact us at support@applymandu.com</p>
        <p>© {{ date('Y') }} ApplyMandu. All rights reserved.</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
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
            background-color: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8fafc;
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
        .highlight {
            background-color: #dbeafe;
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
        <h1>{{ $subject }}</h1>
        <p>Your comprehensive hiring analytics report is ready</p>
    </div>
    
    <div class="content">
        <h2>Hello {{ $employer->first_name }},</h2>
        
        <p>Your analytics report has been generated successfully and is attached to this email.</p>
        
        <div class="highlight">
            <h3>📊 What's Inside Your Report:</h3>
            <ul>
                <li><strong>Performance Overview:</strong> Key metrics and trends</li>
                <li><strong>Application Analytics:</strong> Detailed breakdown of your applications</li>
                <li><strong>Demographic Insights:</strong> Understanding your candidate pool</li>
                <li><strong>Conversion Metrics:</strong> How well your jobs are performing</li>
                <li><strong>Actionable Recommendations:</strong> Data-driven suggestions for improvement</li>
            </ul>
        </div>
        
        <p>This report covers your hiring activity and provides insights to help you optimize your recruitment strategy.</p>
        
        <div class="highlight">
            <h3>🎯 Quick Actions:</h3>
            <p>Based on your data, consider:</p>
            <ul>
                <li>Reviewing job descriptions for top-performing positions</li>
                <li>Optimizing your application process</li>
                <li>Expanding successful recruitment channels</li>
            </ul>
        </div>
        
        <p>
            <a href="{{ config('app.frontend_url') }}/dashboard/employer/analytics-plus" class="button">
                View Live Analytics Dashboard
            </a>
        </p>
        
        <p>If you have any questions about your report or need help interpreting the data, please don't hesitate to reach out to our support team.</p>
        
        <p>Best regards,<br>
        The ApplyMandu Analytics Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated email from ApplyMandu Analytics.</p>
        <p>© {{ date('Y') }} ApplyMandu. All rights reserved.</p>
    </div>
</body>
</html>


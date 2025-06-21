<!-- resources/views/exports/analytics-pdf.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Analytics Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px;}
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Analytics Export</h1>
    <h2>Overview</h2>
    <table>
        @foreach($data['overview'] as $key => $value)
            <tr>
                <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>
    <h2>Top Performing Jobs</h2>
    <table>
        <tr>
            <th>Title</th>
            <th>Views</th>
            <th>Applications</th>
            <th>Conversion Rate</th>
        </tr>
        @foreach($data['topPerformingJobs'] as $job)
            <tr>
                <td>{{ $job['title'] }}</td>
                <td>{{ $job['views'] }}</td>
                <td>{{ $job['applications'] }}</td>
                <td>{{ $job['conversionRate'] }}%</td>
            </tr>
        @endforeach
    </table>
    <!-- Add more sections as needed -->
</body>
</html>

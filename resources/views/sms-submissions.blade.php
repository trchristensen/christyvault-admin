<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Consent Demo Submissions - Christy Vault</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f9fafb;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #1f2937;
        }
        .header p {
            margin: 8px 0 0 0;
            color: #6b7280;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #10b981;
        }
        .stat-label {
            color: #6b7280;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        tr:hover {
            background: #f9fafb;
        }
        .timestamp {
            color: #6b7280;
            font-size: 14px;
        }
        .phone {
            font-family: monospace;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/sms-opt-in" class="back-link">← Back to Opt-in Form</a>
        
        <div class="header">
            <h1>SMS Consent Demo Submissions</h1>
            <p>Employee SMS opt-in records for regulatory compliance review</p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">{{ $submissions->count() }}</div>
                <div class="stat-label">Recent Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $submissions->where('created_at', '>=', now()->today())->count() }}</div>
                <div class="stat-label">Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $submissions->where('created_at', '>=', now()->subDays(7))->count() }}</div>
                <div class="stat-label">This Week</div>
            </div>
        </div>

        @if($submissions->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Employee ID</th>
                        <th>Phone Number</th>
                        <th>Work Email</th>
                        <th>Submitted</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $submission)
                        <tr>
                            <td><strong>{{ $submission->employee_name }}</strong></td>
                            <td>{{ $submission->employee_id ?: 'N/A' }}</td>
                            <td><span class="phone">{{ $submission->phone_number }}</span></td>
                            <td>{{ $submission->work_email }}</td>
                            <td>
                                <div class="timestamp">
                                    {{ $submission->created_at->format('M j, Y') }}<br>
                                    {{ $submission->created_at->format('g:i A') }}
                                </div>
                            </td>
                            <td>
                                <div class="timestamp">{{ $submission->ip_address }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">
                <h3>No submissions yet</h3>
                <p>SMS consent submissions will appear here when employees opt in.</p>
                <a href="/sms-opt-in" style="color: #3b82f6;">Test the opt-in form →</a>
            </div>
        @endif

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #6b7280;">
            <p><strong>Note:</strong> This page demonstrates our SMS consent tracking system for regulatory compliance review. 
            In production, employee consent would be managed through our authenticated employee portal.</p>
        </div>
    </div>
</body>
</html> 
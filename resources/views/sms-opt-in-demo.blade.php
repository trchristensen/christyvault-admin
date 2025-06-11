<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Christy Vault - Employee SMS Notifications</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            color: #333;
            background: #f9fafb;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #6b7280;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        input[type="text"], input[type="tel"], input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .consent-section {
            background: #f3f4f6;
            padding: 24px;
            border-radius: 8px;
            margin: 24px 0;
            border-left: 4px solid #3b82f6;
        }
        .consent-section h3 {
            margin: 0 0 16px 0;
            color: #1f2937;
        }
        .use-cases {
            margin: 16px 0;
            padding-left: 20px;
        }
        .use-cases li {
            margin-bottom: 8px;
            color: #4b5563;
        }
        .consent-checkbox {
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .consent-checkbox input[type="checkbox"] {
            margin-top: 4px;
            transform: scale(1.2);
        }
        .submit-btn {
            background: #10b981;
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s;
        }
        .submit-btn:hover {
            background: #059669;
        }
        .submit-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .business-info {
            background: #fef3c7;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            border-left: 4px solid #f59e0b;
        }
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .demo-notice {
            background: #dbeafe;
            border: 2px solid #3b82f6;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            text-align: center;
        }
        .demo-notice strong {
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="demo-notice">
            <strong>DEMONSTRATION PAGE</strong><br>
            This page demonstrates our employee SMS opt-in process for regulatory compliance review.
        </div>

        <div class="header">
            <div class="logo">🏛️ Christy Vault</div>
            <div class="subtitle">Employee Work Notification System</div>
        </div>

        <div class="business-info">
            <h3>📋 Business Use Case</h3>
            <p><strong>Internal Employee Communications Only</strong></p>
            <p>This SMS system is exclusively for work-related communications to our delivery drivers and field employees. We send:</p>
            <ul>
                <li>Daily work schedules and assignments</li>
                <li>Delivery confirmations and updates</li>
                <li>Time-sensitive work notifications</li>
                <li>Job completion confirmations</li>
            </ul>
            <p><em>No marketing, promotional, or non-work related messages are sent.</em></p>
        </div>

        <form id="optInForm">
            <div class="form-group">
                <label for="employee_name">Employee Name:</label>
                <input type="text" id="employee_name" name="employee_name" required placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label for="employee_id">Employee ID:</label>
                <input type="text" id="employee_id" name="employee_id" required placeholder="Your employee ID number">
            </div>

            <div class="form-group">
                <label for="phone_number">Mobile Phone Number:</label>
                <input type="tel" id="phone_number" name="phone_number" required placeholder="(555) 123-4567">
            </div>

            <div class="form-group">
                <label for="work_email">Work Email:</label>
                <input type="email" id="work_email" name="work_email" required placeholder="employee@christyvault.com">
            </div>

            <div class="consent-section">
                <h3>📱 Work SMS Notifications</h3>
                <p>By providing your phone number, you agree to receive work-related text messages from Christy Vault including:</p>
                <ul class="use-cases">
                    <li><strong>Daily work schedules</strong> - Your assigned tasks and delivery routes</li>
                    <li><strong>Assignment updates</strong> - When new work is assigned to you</li>
                    <li><strong>Delivery confirmations</strong> - Links to complete job confirmations</li>
                    <li><strong>Time-sensitive alerts</strong> - Urgent work-related notifications</li>
                </ul>
                
                <p><strong>IMPORTANT:</strong></p>
                <ul class="use-cases">
                    <li>This is for <strong>employment-related communications only</strong></li>
                    <li>No marketing or promotional messages will be sent</li>
                    <li>You can opt out anytime by texting <strong>STOP</strong></li>
                    <li>Standard message and data rates may apply</li>
                </ul>
                
                <div class="consent-checkbox">
                    <input type="checkbox" id="consent_required" required>
                    <label for="consent_required">
                        I agree to receive work-related SMS messages from Christy Vault at the phone number provided above. I understand these are business communications related to my employment and job duties.
                    </label>
                </div>

                <div class="consent-checkbox">
                    <input type="checkbox" id="consent_terms" required>
                    <label for="consent_terms">
                        I understand I can stop receiving messages at any time by texting "STOP" or contacting my supervisor.
                    </label>
                </div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn" disabled>
                ✅ Agree & Setup Work SMS Notifications
            </button>
        </form>

        <div class="footer">
            <p><strong>Christy Vault</strong><br>
            Employee Communication System<br>
            For questions, contact HR or your supervisor.</p>
            
            <p style="margin-top: 15px;">
                <strong>Business Address:</strong> 1000 Collins Ave, Colma, CA 94014<br>
                <strong>Phone:</strong> +1 (888) 880-0017<br>
                <strong>Email:</strong> tchristensen@christyvault.com
            </p>
            
            <p style="margin-top: 15px;">
                <small>This consent is for employment-related communications only. 
                Standard message and data rates may apply. 
                You can opt out at any time by texting STOP.</small>
            </p>
        </div>
    </div>

    <script>
        const form = document.getElementById('optInForm');
        const requiredConsent = document.getElementById('consent_required');
        const termsConsent = document.getElementById('consent_terms');
        const submitBtn = document.getElementById('submitBtn');

        // Enable submit button only when both consents are checked
        function updateSubmitButton() {
            submitBtn.disabled = !(requiredConsent.checked && termsConsent.checked);
        }

        requiredConsent.addEventListener('change', updateSubmitButton);
        termsConsent.addEventListener('change', updateSubmitButton);

        // Handle form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            // Submit to backend
            fetch('/sms-opt-in', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    document.querySelector('.container').innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 48px; margin-bottom: 20px;">✅</div>
                            <h2 style="color: #10b981;">SMS Work Notifications Enabled!</h2>
                            <p>Your consent has been recorded and you will now receive work-related notifications via text message.</p>
                            <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin-top: 20px;">
                                <p><strong>Confirmation Details:</strong></p>
                                <p><strong>Employee:</strong> ${data.data.employee_name}</p>
                                <p><strong>Phone:</strong> ${data.data.phone_number}</p>
                                <p><strong>Email:</strong> ${data.data.work_email}</p>
                                <p><strong>Recorded:</strong> ${new Date(data.timestamp).toLocaleString()}</p>
                            </div>
                            <div style="background: #dbeafe; padding: 16px; border-radius: 8px; margin-top: 20px; border: 2px solid #3b82f6;">
                                <p style="margin: 0; color: #1e40af;"><strong>DEMONSTRATION COMPLETE</strong></p>
                                <p style="margin: 8px 0 0 0; font-size: 14px; color: #1e40af;">This submission has been recorded in our database for regulatory compliance review.</p>
                            </div>
                        </div>
                    `;
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✅ Agree & Setup Work SMS Notifications';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error submitting form. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = '✅ Agree & Setup Work SMS Notifications';
            });
        });

        // Format phone number as user types
        document.getElementById('phone_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.substring(0,3) + '-' + value.substring(3,6) + '-' + value.substring(6,10);
            } else if (value.length >= 3) {
                value = value.substring(0,3) + '-' + value.substring(3);
            }
            e.target.value = value;
        });
    </script>
</body>
</html> 
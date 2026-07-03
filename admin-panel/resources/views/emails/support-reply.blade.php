<!DOCTYPE html>
<html>
<head>
    <title>Support Ticket Reply</title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background-color: #4F46E5; padding: 20px; color: white; text-align: center;">
            <h1 style="margin: 0;">Support Ticket Update</h1>
        </div>
        
        <div style="padding: 30px;">
            <p>Dear {{ $restaurant->name ?? 'Restaurant Owner' }},</p>
            
            <p>You have received a new reply to your support ticket <strong>#{{ $ticket->ticket_number }}</strong>.</p>
            
            <div style="background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Ticket: {{ $ticket->subject }}</h3>
                <p><strong>Status:</strong> {{ ucfirst($ticket->status) }}</p>
            </div>
            
            <div style="background-color: #e5e7eb; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <p><strong>New Reply:</strong></p>
                <p>{{ $reply->message }}</p>
            </div>
            
            <p>You can view and reply to this ticket by clicking the button below:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ route('restaurant.support.show', $ticket->id) }}" 
                   style="background-color: #4F46E5; color: white; padding: 12px 30px; 
                          text-decoration: none; border-radius: 5px; display: inline-block;">
                    View Ticket
                </a>
            </div>
            
            <hr style="margin: 20px 0;">
            <p style="color: #6b7280; font-size: 12px;">
                This is an automated message. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>

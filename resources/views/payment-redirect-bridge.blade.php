<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Processing Payment...</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <h1>Processing complete. Redirecting...</h1>
    <script>
        const status = '{{ $status }}';
        const reference = '{{ $reference }}';
        const sessionId = '{{ $sessionId }}';
        const errorMsg = '{{ $errorMsg }}';

        // 1. Determine the target path in the frontend iFrame application
        let targetPath = '/booking-flow/failure';
        if (status === 'success') {
            targetPath = '/booking-flow/success';
        } else if (status === 'error') {
            // Use the failure path but include critical error messages
            targetPath = `/booking-flow/failure?msg=${encodeURIComponent(errorMsg)}&txId=${sessionId}`;
        }
        
        // 2. The critical step: Redirect the PARENT iFrame to the target path.
        // We ensure we redirect the top-level parent if necessary, but here we 
        // target the parent window, which should be the main BookingWidget iframe 
        // that initiated the PhonePe redirection.
        
        // This attempts to navigate the *current* window (the inner PhonePe frame)
        // to the success/failure URL within your Vue router.
        window.location.href = '{{ config('app.frontend_url') }}' + targetPath;

        // Note: If the payment gateway loads its final redirect page at the TOP-LEVEL
        // (breaking out of the iFrame), this script will execute in the *main browser window*.
        // A robust solution involves checking window.top != window for security breaches
        // and using window.postMessage to talk to the original host site, but since
        // PhonePe is loading inside your Vue iFrame, this simple internal redirect
        // should now work because it's navigating *within* the Vue router's context.

    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting...</title>
@include('partials.public-blade-polish')
</head>
<body>
    <p>{{ $message ?? 'Redirecting...' }}</p>
    <script>
        try {
            localStorage.removeItem('checkout_cart');
            localStorage.removeItem('checkout_restaurant_id');
        } catch (_) {}
        window.location.replace(@json($redirectUrl));
    </script>
@include('partials.web-visit-tracker', ['panel' => 'checkout'])
</body>
</html>

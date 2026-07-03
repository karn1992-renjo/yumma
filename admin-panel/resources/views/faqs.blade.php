@php
    $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs | {{ $appName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@include('partials.public-blade-polish')
</head>
<body class="bg-light">
    <main class="container py-5">
        <a href="{{ route('home') }}" class="text-decoration-none mb-4 d-inline-block">&larr; Back to home</a>
        <div class="bg-white shadow-sm rounded-4 p-5">
            <h1 class="display-6 fw-bold">Frequently Asked Questions</h1>
            <p class="lead text-muted">Answers to common questions about ordering, shipping, payments and support.</p>

            <div class="accordion mt-4" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqHeading1">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false" aria-controls="faq1">
                            How do I place an order?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" aria-labelledby="faqHeading1" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">Search for a restaurant or cuisine, add items to your cart, then choose delivery and payment options to place your order.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqHeading2">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                            What payment methods are accepted?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" aria-labelledby="faqHeading2" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">We accept a variety of payment options including credit/debit cards, digital wallets and cash on delivery depending on the restaurant.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqHeading3">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                            How can I contact support?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" aria-labelledby="faqHeading3" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">Use the support form from the Contact page or submit a ticket through the Help Center.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqHeading4">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                            Can I track my delivery in real time?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" aria-labelledby="faqHeading4" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">Yes. After your order is confirmed, you can view tracking and order status updates from the customer dashboard.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqHeading5">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                            How do I become a partner?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" aria-labelledby="faqHeading5" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">Visit the homepage Partner modal and choose Restaurant Partner or Delivery Partner to register with us.</div>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <a href="{{ route('support.create') }}" class="btn btn-primary">Need more help?</a>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>

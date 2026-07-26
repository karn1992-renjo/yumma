# Buy X Get Y Promotion Workflow PRD

## 1. Purpose

Build an enterprise-grade Buy X Get Y promotion workflow for food delivery, restaurant self-service, admin campaigns, customer discovery, cart evaluation, checkout validation, fulfillment, and analytics.

The workflow must support food-delivery patterns similar to Swiggy, Zomato, Uber Eats, Talabat, Foodpanda, Shopify Discounts, and Adobe Commerce:

- automatic and coupon-based promotions
- item, category, restaurant, combo, meal, variant, addon, and tag targeting
- free item, cheapest item, percentage, flat, wallet, and points rewards
- live cart progress
- reward selection
- checkout revalidation
- usage, budget, inventory, and analytics tracking at scale

This document is the product and implementation contract for Laravel backend, admin web, restaurant web/app, and Flutter customer app.

## 2. Current system alignment

Current backend foundation:

- `promotions` is the source of truth for promotion metadata and JSON rule payloads.
- `targets`, `conditions`, `rewards`, `schedule`, `stacking`, and `visibility` JSON columns hold rule configuration.
- `promotion_coupon_codes` stores coupon codes.
- `promotion_usage` records order-level redemption and savings.
- `promotion_logs` records calculation/validation events.
- Existing service facade: `PromotionEngineService`.
- Existing supporting services: `PromotionFinder`, `PromotionValidator`, `PromotionCalculator`, `PromotionRewardService`, `PromotionStackService`, `PromotionLogger`, `PromotionAnalyticsService`, `PromotionPreviewService`.
- Existing docs: `admin-panel/docs/promotion-engine-enterprise-upgrade.md`.

Required extension:

- Add a dedicated Buy X Get Y rule schema inside `conditions` and `rewards`.
- Add cart-level reward line output, not just discount amount.
- Add reward selection APIs.
- Add inventory/budget locks at checkout/payment.
- Add customer UI progress and reward selection.
- Add analytics dimensions for buy rule, reward rule, reward item, free quantity, and breakage.

## 3. Promotion types

Each type is represented by `promotion_type` plus structured `conditions.buy_rule` and `rewards.reward_rule`.

| Type | Intent | Example |
| --- | --- | --- |
| `bogo` | Buy 1 qualifying unit, get 1 reward unit | Buy 1 burger get 1 burger free |
| `buy_2_get_1` | Buy 2 units, get 1 reward | Buy 2 pizzas get 1 garlic bread |
| `buy_3_get_2` | Buy 3 units, get 2 rewards | Buy 3 drinks get 2 free |
| `buy_x_get_y` | Configurable buy quantity and reward quantity | Buy 4 momos get 2 dips |
| `buy_category_get_item` | Buy from category, get specific item | Buy pizza get Coke |
| `buy_category_get_category` | Buy category, get item from reward category | Buy mains get dessert |
| `buy_restaurant_item_get_free_item` | Restaurant menu item unlocks free item | Buy thali get gulab jamun |
| `buy_amount_get_free_item` | Cart amount unlocks free item | Spend 499 get fries |
| `buy_combo_get_reward` | Combo purchase unlocks reward | Buy family combo get drink |
| `buy_meal_get_dessert` | Meal purchase unlocks dessert | Buy meal get brownie |
| `buy_pizza_get_drink` | Category preset | Buy pizza get drink |
| `buy_burger_get_fries` | Category preset | Buy burger get fries |
| `buy_product_a_get_product_b` | Specific SKU to specific SKU | Buy Burger A get Fries B |
| `buy_any_2_get_cheapest_free` | Cheapest qualifying cart item free | Buy any 2 desserts, cheapest free |
| `buy_n_items_get_cheapest_discount` | Cheapest qualifying item gets discount | Buy 5 snacks, cheapest 50% off |
| `buy_quantity_get_wallet_cashback` | Quantity unlocks wallet cashback | Buy 3 pizzas get 50 wallet cash |
| `buy_quantity_get_reward_points` | Quantity unlocks points | Buy 2 meals earn 100 points |

## 4. Roles and ownership

Admin can create:

- platform-funded offers
- cross-restaurant campaigns
- free delivery, delivery discount, packaging discount
- wallet cashback, reward points, scratch cards, gift vouchers
- referral bonus, festival offers, flash sales
- custom rules
- restaurant-specific campaigns on behalf of restaurant

Restaurant can create:

- restaurant-funded item/category/combo/meal promotions
- percentage or flat restaurant offers
- BOGO and Buy X Get Y offers for its own menu
- free item/drink/dessert for its own menu
- happy hours and deal-of-day for its own restaurant

Restaurant cannot create:

- payment method offers
- bank/card offers
- platform wallet liability unless allowed by admin
- delivery, packaging, global referral, scratch card, gift voucher, reward point campaigns unless delegated by admin.

## 5. Admin creation workflow

### 5.1 Builder steps

1. General information
2. Ownership and campaign
3. Buy rule
4. Reward rule
5. Reward settings
6. Conditions
7. Schedule and budget
8. Stacking
9. Visibility
10. Preview and simulation
11. Publish

### 5.2 General information fields

- Promotion name
- Banner image
- Thumbnail image
- Description
- Promotion type
- Application mode: `automatic` or `coupon`
- Coupon code strategy: single, bulk, user-assigned, public
- Owner: admin or restaurant
- Restaurant or restaurant group
- Campaign
- Priority
- Status: draft, active, paused, archived
- Start date and end date
- Daily start time and end time
- Budget
- Total usage limit
- Per-user usage limit
- Per-order usage limit

### 5.3 Buy rule fields

`conditions.buy_rule` should support:

```json
{
  "buy_quantity": 2,
  "min_quantity": 2,
  "max_quantity": 20,
  "min_amount": 0,
  "max_amount": null,
  "scope": "same_item",
  "item_ids": [101, 102],
  "category_ids": [7],
  "brand_ids": [],
  "restaurant_ids": [15],
  "combo_ids": [],
  "meal_ids": [],
  "variant_ids": [],
  "addon_ids": [],
  "menu_tags": ["burger", "bestseller"],
  "food_types": ["veg", "non_veg"],
  "match_strategy": "any",
  "quantity_grouping": "per_item"
}
```

Supported `scope` values:

- `same_item`
- `specific_items`
- `any_item`
- `category`
- `restaurant`
- `combo`
- `meal`
- `variant`
- `addon`
- `menu_tag`
- `food_type`
- `cart_amount`

Supported `quantity_grouping`:

- `per_item`: sets are calculated separately per item.
- `across_items`: all eligible quantities are pooled.
- `per_category`: sets are calculated per category.
- `per_restaurant`: sets are calculated for entire restaurant cart.

### 5.4 Reward rule fields

`rewards.reward_rule` should support:

```json
{
  "reward_type": "specific_item",
  "reward_quantity": 1,
  "max_reward_quantity": 3,
  "reward_value": 0,
  "reward_discount_type": "free",
  "reward_discount_value": 100,
  "reward_priority": "cheapest",
  "item_ids": [201],
  "category_ids": [11],
  "variant_ids": [],
  "addon_ids": [],
  "auto_add": true,
  "manual_selection": false,
  "selection_required": false,
  "fallback_strategy": "same_category",
  "out_of_stock_strategy": "suggest_replacement"
}
```

Reward types:

- `same_item`
- `specific_item`
- `any_item`
- `category`
- `drink`
- `dessert`
- `addon`
- `variant`
- `wallet_cashback`
- `reward_points`
- `percentage_discount`
- `flat_discount`

Reward priority:

- `configured_order`
- `cheapest`
- `most_expensive`
- `same_as_trigger`
- `customer_choice`

Reward discount type:

- `free`
- `percentage`
- `flat`
- `fixed_price`
- `cashback`
- `points`

## 6. Customer journey

### 6.1 Home

UI:

- Dedicated built-in section per promotion type.
- Each section can be reordered/hidden in home section management.
- Admin can change section title and subtitle.
- Cards should use menu item imagery, not generic promo-only cards.
- Combo cards show multiple menu images in carousel.
- Category-selected promotions show first row with main promo cards and second row with category mini cards.

Flutter widgets:

- `PromotionSectionRail`
- `PromotionHeroCard`
- `PromotionMenuItemCard`
- `PromotionComboCard`
- `PromotionCategoryMiniCard`

Backend:

- `HomeSectionService` returns `promotion_type_section`.
- Payload must include `display_mode`, `menu_items`, `promotion_categories`, reward config, images, and CTA target.

### 6.2 Promotion details

Screen content:

- Banner
- Promotion name
- Description
- How it works
- Eligible restaurants
- Eligible items/categories
- Reward details
- Countdown
- Terms
- Savings example
- Start ordering button

Flutter widgets:

- `PromotionDetailScreen`
- `PromotionHowItWorks`
- `EligibleRestaurantGrid`
- `EligibleItemGrid`
- `PromotionTermsPanel`
- `SavingsExampleCard`

API:

- `GET /api/promotions/{id}`
- Include current eligibility preview when user/cart context exists.

### 6.3 Restaurant details and menu

Eligible menu items display badges:

- `BUY 2 GET 1`
- `FREE DRINK`
- `FREE DESSERT`
- `COMBO ELIGIBLE`
- `CHEAPEST FREE`

Flutter widgets:

- Existing `MenuItemCard` should receive `promotionTag`.
- Restaurant card should display active promotion label.
- Menu item details should show exact rule and progress.

Backend:

- Restaurant menu API should include `active_promos`.
- Each promo should expose target ids, reward ids, category ids, and display label.

### 6.4 Cart

Cart shows:

- Promotion progress card
- Item-level promotion tags
- Reward line items
- Free item rows
- Manual reward selection CTA
- Invalid reward warning
- Promotion discount/cashback/points rows

Example states:

- Not eligible: `Need 1 more Burger to unlock FREE Coke`
- Amount gap: `Add INR 150 more to unlock Garlic Bread`
- Eligible no reward selected: `Choose your free drink`
- Auto-added: `Free Coke added`
- Invalid after removal: `Free Coke removed because Burger quantity changed`

Flutter widgets:

- `CartPromotionProgressCard`
- `CartRewardLineItem`
- `RewardSelectionBottomSheet`
- `PromotionInvalidationDialog`

### 6.5 Checkout

Checkout must revalidate:

- promotion status
- coupon
- time window
- restaurant status
- item availability
- reward availability
- inventory
- usage limit
- budget
- stacking conflict
- cart mutation

Checkout UI:

- Applied promotions
- Reward lines
- Savings
- Cashback
- Reward points
- Terms acknowledgement when needed

### 6.6 Payment

Before payment session creation:

- Recalculate promotion.
- Lock budget and usage counters.
- Reserve reward inventory when applicable.
- Persist a promotion snapshot on pending order.

If payment fails:

- Release budget reservation.
- Release reward inventory reservation.
- Keep user-selected reward in cart for retry if still valid.

### 6.7 Order success

Show:

- Reward earned
- Free items given
- Promotion used
- Total savings
- Cashback pending/credited
- Reward points earned
- Voucher/scratch card reveal if applicable

## 7. Cart engine flow

### 7.1 Calculation phases

1. Normalize cart context.
2. Fetch candidate promotions by restaurant, zone, owner, status, date, and visibility.
3. Validate targets.
4. Validate buy rules.
5. Build eligible buy groups.
6. Calculate eligible set count.
7. Determine reward candidates.
8. Resolve reward selection mode.
9. Calculate discount/cashback/points/free lines.
10. Apply stacking.
11. Apply budget/usage dry-run checks.
12. Return summary, progress, invalid reasons, and actions.

### 7.2 Output contract

```json
{
  "subtotal": 760,
  "promotion_discount": 180,
  "delivery_discount": 0,
  "packaging_discount": 0,
  "cashback_amount": 0,
  "reward_points": 0,
  "tax": 38,
  "delivery_fee": 30,
  "packaging_fee": 10,
  "total": 658,
  "applied_promotions": [],
  "eligible_promotions": [],
  "reward_lines": [],
  "progress": [],
  "invalid_reasons": []
}
```

### 7.3 Auto-add workflow

Use auto-add when:

- exactly one reward item is available
- reward item has stock
- reward is deterministic
- promotion allows `auto_add=true`

Flow:

1. User adds second burger.
2. Engine detects Buy 2 Get 1.
3. Reward candidate list has one Coke.
4. Cart inserts Coke with `line_type=promotion_reward`, `unit_price=0`.
5. Show `Free Coke added`.
6. If user removes trigger item, remove reward and show confirmation.

### 7.4 Manual reward selection workflow

Use manual selection when:

- multiple reward items exist
- customer choice is configured
- reward category is broad
- variants/addons require selection

Flow:

1. Cart becomes eligible.
2. Engine returns `action=select_reward`.
3. App opens bottom sheet.
4. User chooses Coke, Sprite, Fanta, or Water.
5. App calls reward selection API.
6. Engine validates selection.
7. Cart inserts selected reward.

## 8. Quantity examples

### 8.1 Buy 2 Get 1, same item

Rule:

- Buy quantity: 2
- Reward quantity: 1
- Grouping: same item
- Max reward: 3

Cart:

- 6 burgers at INR 180

Calculation:

- Set size: 3 units
- Eligible sets: `floor(6 / 3) = 2`
- Free units: `2 * 1 = 2`
- Reward limit: `min(2, 3) = 2`
- Discount: `2 * 180 = INR 360`

### 8.2 Buy any 2 get cheapest free

Cart:

- Burger INR 180
- Fries INR 90
- Coke INR 60

Rule:

- Buy quantity: 2
- Reward priority: cheapest
- Grouping: across items

Calculation:

- Eligible units sorted ascending: Coke 60, Fries 90, Burger 180
- Set size: 3 if buy 2 get 1
- Free unit: Coke
- Discount: INR 60

### 8.3 Buy amount get free item

Rule:

- Minimum amount: INR 499
- Reward: Garlic Bread
- Auto add: true

Cart:

- Subtotal INR 349: progress says `Add INR 150 more`
- Subtotal INR 520: reward line Garlic Bread INR 0

### 8.4 Buy quantity get wallet cashback

Rule:

- Buy 3 pizzas
- Reward: INR 75 wallet cashback
- Max reward per order: 1

Cart:

- 4 pizzas qualifies once.
- Total payable is unchanged.
- Order success shows `INR 75 cashback pending`.
- Wallet is credited after order delivered, not at cart time.

## 9. Stacking

Stacking policy fields:

```json
{
  "exclusive": false,
  "stackable_with": ["free_delivery", "cashback"],
  "not_stackable_with": ["bank_offer"],
  "max_promotions": 2,
  "selection_strategy": "best_savings",
  "apply_order": [
    "item_discount",
    "cart_discount",
    "delivery_discount",
    "cashback",
    "reward_points",
    "bank_offer"
  ]
}
```

Rules:

- Item/free rewards apply before cart percentage discounts.
- Free delivery applies after delivery fee calculation.
- Cashback and points do not reduce payable total.
- Bank/payment offers apply after payment method selection.
- Exclusive promotions suppress lower-priority promotions.
- Coupon promotions require explicit coupon validation unless `coupon_auto_apply=true`.

## 10. Failure reasons

The engine must return machine-readable reason codes plus user-friendly messages.

| Code | Message |
| --- | --- |
| `min_quantity_not_met` | Add N more eligible item(s). |
| `max_quantity_exceeded` | Offer applies only up to N items. |
| `min_amount_not_met` | Add INR N more to unlock this offer. |
| `max_amount_exceeded` | Cart value is above this offer limit. |
| `restaurant_mismatch` | Offer is not valid for this restaurant. |
| `category_mismatch` | Add eligible category items. |
| `item_mismatch` | Add eligible menu items. |
| `variant_mismatch` | Offer requires a specific variant. |
| `addon_mismatch` | Offer requires a specific addon. |
| `reward_unavailable` | Reward item is unavailable. |
| `reward_out_of_stock` | Reward is out of stock. |
| `budget_exhausted` | Offer budget is exhausted. |
| `coupon_required` | Apply coupon to unlock this offer. |
| `coupon_expired` | Coupon has expired. |
| `usage_limit_reached` | Offer usage limit reached. |
| `per_user_limit_reached` | You already used this offer. |
| `promotion_paused` | Offer is paused. |
| `promotion_expired` | Offer has expired. |
| `restaurant_closed` | Restaurant is currently closed. |
| `stack_conflict` | Better offer already applied. |

## 11. Database design

### 11.1 Existing tables to keep

- `promotions`
- `promotion_coupon_codes`
- `promotion_targets`
- `promotion_conditions`
- `promotion_rewards`
- `promotion_usage`
- `promotion_logs`

### 11.2 Recommended extension tables

`promotion_budget_ledger`

- `id`
- `promotion_id`
- `order_id`
- `reservation_key`
- `amount_reserved`
- `amount_consumed`
- `status`: reserved, consumed, released, expired
- `expires_at`
- indexes: `promotion_id,status`, `reservation_key`

`promotion_reward_selections`

- `id`
- `cart_token`
- `user_id`
- `promotion_id`
- `restaurant_id`
- `reward_item_id`
- `reward_variant_id`
- `reward_addon_ids`
- `quantity`
- `selection_hash`
- `expires_at`
- indexes: `cart_token,promotion_id`, `user_id,promotion_id`

`promotion_inventory_reservations`

- `id`
- `promotion_id`
- `order_id`
- `menu_item_id`
- `variant_id`
- `quantity`
- `status`
- `expires_at`
- indexes: `menu_item_id,status`, `order_id,promotion_id`

`order_promotion_snapshots`

- `id`
- `order_id`
- `promotion_id`
- `coupon_code`
- `rule_snapshot`
- `calculation_snapshot`
- `reward_snapshot`
- `discount_amount`
- `cashback_amount`
- `reward_points`
- indexes: `order_id`, `promotion_id`

### 11.3 Index requirements

For millions of users:

- `promotions(status, starts_at, ends_at)`
- `promotions(restaurant_id, status)`
- `promotions(owner_type, owner_id)`
- `promotions(promotion_type, status)`
- `promotion_coupon_codes(code, is_active)`
- `promotion_usage(promotion_id, user_id)`
- `promotion_usage(order_id, promotion_id)`
- `promotion_logs(event_type, created_at)`
- budget reservation indexes listed above

## 12. APIs

### 12.1 Available promotions

`GET /api/promotions`

Query:

- `restaurant_id`
- `promotion_type`
- `service_type`
- `zone_id`
- `limit`

Returns list cards for home and offers.

### 12.2 Promotion details

`GET /api/promotions/{id}`

Returns:

- promotion payload
- eligible restaurants
- eligible items
- reward candidates
- terms
- examples

### 12.3 Preview promotion

`POST /api/promotions/{id}/preview`

Input:

- cart items
- restaurant id
- user id or guest token
- order type
- address/location

Returns:

- eligible
- progress
- reward candidates
- estimated savings
- invalid reasons

### 12.4 Validate cart promotions

`POST /api/promotions/validate`

Returns the full cart calculation with applied/eligible/invalid promotions.

### 12.5 Apply coupon promotion

`POST /api/promotions/apply`

Input:

- `coupon_code`
- cart context

Returns validated calculation.

### 12.6 Remove promotion

`POST /api/promotions/remove`

Input:

- promotion id or coupon code
- cart context

Returns recalculated cart.

### 12.7 Reward selection

`POST /api/promotions/{id}/reward-selection`

Input:

```json
{
  "cart_token": "abc",
  "reward_item_id": 201,
  "variant_id": null,
  "addon_ids": [],
  "quantity": 1
}
```

Returns:

- selected reward line
- updated calculation
- selection expiry

### 12.8 Checkout lock

`POST /api/promotions/checkout-lock`

Purpose:

- reserve budget
- reserve reward inventory
- persist calculation hash

### 12.9 Checkout release

`POST /api/promotions/checkout-release`

Purpose:

- release budget and inventory if payment fails, cart expires, or order is cancelled.

## 13. Laravel implementation design

### 13.1 Services

`PromotionEngineService`

- facade for list, calculate, apply, remove, record usage

`PromotionFinder`

- candidate fetch using indexed filters
- avoids scanning all promotions

`PromotionValidator`

- validates status, time, target, buy rule, coupon, usage, budget, audience

`BuyXGetYRuleEvaluator`

- builds eligible buy groups
- calculates set count
- generates progress
- handles same item, category, amount, combo, meal, variant, addon, tag

`RewardResolver`

- resolves reward candidates
- applies cheapest/most-expensive/configured/customer-choice selection
- handles stock fallback

`PromotionCalculator`

- converts resolved rewards into discount/cashback/points/reward lines

`PromotionStackService`

- applies stacking rules and conflict resolution

`PromotionBudgetService`

- dry-run budget check
- checkout reservation
- consume/release reservation

`PromotionInventoryReservationService`

- reserve reward item inventory
- release or consume on payment/order state change

`PromotionSnapshotService`

- stores order promotion snapshots
- prevents historical orders changing if promotion changes later

`PromotionAnalyticsService`

- calculates usage, revenue, cost, conversion, ROI

### 13.2 Engine sequence

```text
Customer App -> PromotionController: validate cart
PromotionController -> PromotionEngineService: calculate(context)
PromotionEngineService -> PromotionFinder: candidates(context)
PromotionEngineService -> PromotionValidator: check(promotion, context)
PromotionValidator -> BuyXGetYRuleEvaluator: evaluate buy rule
BuyXGetYRuleEvaluator -> RewardResolver: reward candidates/progress
RewardResolver -> PromotionCalculator: reward lines/discounts
PromotionCalculator -> PromotionStackService: select applicable lines
PromotionStackService -> PromotionEngineService: final lines
PromotionEngineService -> PromotionLogger: log calculation
PromotionEngineService -> Customer App: summary, rewards, progress
```

### 13.3 Checkout sequence

```text
Customer App -> Checkout API: place order
Checkout API -> PromotionEngineService: recalculate
Checkout API -> PromotionBudgetService: reserve
Checkout API -> InventoryReservationService: reserve rewards
Checkout API -> OrderService: create pending order
Checkout API -> Payment Gateway: create session
Payment Gateway -> Checkout API: success/failure
Checkout API -> PromotionBudgetService: consume or release
Checkout API -> InventoryReservationService: consume or release
Checkout API -> PromotionEngineService: recordUsage
Checkout API -> Customer App: order success/failure
```

## 14. Flutter implementation design

### 14.1 Models

- `Promotion`
- `PromotionRule`
- `PromotionReward`
- `PromotionProgress`
- `PromotionRewardCandidate`
- `PromotionCartLine`
- `PromotionCalculation`

### 14.2 Services

- `PromotionApiService`
- `CartPromotionService`
- `RewardSelectionService`

### 14.3 Widgets

Home:

- `PromotionTypeSection`
- `PromotionCard`
- `PromotionComboCard`
- `PromotionCategoryRow`

Details:

- `PromotionDetailsScreen`
- `PromotionHowItWorks`
- `EligibleItemsGrid`
- `EligibleRestaurantsGrid`
- `PromotionTerms`

Menu:

- `PromotionBadge`
- `MenuItemPromotionHint`
- `MenuPromotionProgressMini`

Cart:

- `CartPromotionProgressCard`
- `RewardSelectionBottomSheet`
- `FreeRewardCartItem`
- `PromotionInvalidationDialog`

Checkout:

- `CheckoutPromotionSummary`
- `PromotionSavingsRow`
- `CashbackPointsRow`
- `RewardSnapshotCard`

Order success:

- `PromotionSuccessCard`
- `CashbackEarnedCard`
- `RewardPointsEarnedCard`
- `ScratchCardReveal`

## 15. UX best practices

- Never hide why an offer is not active.
- Always show the shortest next action: `Add 1 more`, `Choose reward`, `Apply coupon`.
- Auto-add only deterministic rewards.
- Manual selection must show unavailable choices disabled with reason.
- Free item line should show original price struck through and `FREE`.
- Removing trigger items should warn before removing reward.
- If promotion expires, show a non-blocking banner and recalculate cart.
- If budget is exhausted at checkout, explain and show next best offer.
- Avoid showing admin-only promotions in restaurant self-service.
- Avoid showing legacy coupons in home promotion sections.

## 16. Analytics

### 16.1 Admin analytics

Metrics:

- impressions
- clicks
- promotion detail views
- add-to-cart from promotion
- eligible carts
- applied carts
- orders
- revenue
- discount given
- cashback liability
- reward points issued
- free items given
- conversion rate
- attach rate
- budget consumed
- ROI
- average order value uplift
- top restaurants
- top rewards
- top categories
- invalid reason distribution
- stock-out losses

### 16.2 Restaurant analytics

Metrics:

- promotion performance
- reward cost
- revenue generated
- incremental orders
- popular rewards
- free item quantity
- conversion from menu badge
- cart abandonment after ineligibility
- day/time performance
- item-level profitability

## 17. Edge cases

Cart update:

- Recalculate immediately after quantity, variant, addon, or item removal.

Duplicate reward:

- Reward lines must be identified by `promotion_id + reward_item_id + set_index`.

Reward deleted:

- Mark promotion invalid and show replacement if configured.

Reward out of stock:

- Use fallback strategy. If no fallback, remove reward and explain.

Restaurant closed:

- Do not allow checkout. Keep promotion preview visible but disabled.

Menu disabled:

- Exclude disabled trigger and reward items.

Promotion paused:

- Remove from eligible list and cart.

Budget exhausted:

- At cart: show possible but not guaranteed if budget not locked.
- At checkout: hard fail and recalculate.

Priority conflict:

- Apply configured priority first unless `best_savings` stacking is enabled.

Stack conflict:

- Return suppressed promotions with reason `stack_conflict`.

Promotion expires while cart is open:

- Revalidate on app resume, checkout open, and payment start.

Variant-specific reward:

- Reward line must include selected variant id and price basis.

Addon reward:

- Addon can attach to trigger item or reward item depending on rule.

Multiple restaurants:

- Food cart should remain single restaurant. If multi-restaurant is added later, evaluate per restaurant first, then platform-level cart offers.

## 18. Acceptance criteria

- Admin can create all Buy X Get Y variants with dynamic fields.
- Restaurant can create only restaurant-allowed variants.
- Home sections show promotion-engine offers by promotion type.
- Legacy promo codes do not appear in home promotion sections.
- Promotion details screen explains rule, reward, terms, and eligible items.
- Menu items show correct badges for item/category/restaurant matching.
- Cart shows progress before eligibility.
- Cart auto-adds deterministic reward items.
- Cart supports manual reward selection.
- Removing trigger items removes invalid rewards.
- Checkout revalidates promotion and locks budget/inventory.
- Order success shows savings, rewards, cashback, and points.
- Analytics report usage, revenue, reward cost, ROI, and reward popularity.
- Engine returns structured invalid reasons for all failed conditions.

## 19. Rollout plan

Phase 1:

- Backend rule schema and evaluator.
- Cart preview API.
- Flutter cart progress and item tags.

Phase 2:

- Reward selection API.
- Auto-add and manual reward cart lines.
- Checkout lock/release.

Phase 3:

- Admin advanced builder.
- Restaurant scoped builder.
- Promotion details screen.

Phase 4:

- Analytics dashboards.
- Budget ledger and inventory reservation reporting.
- A/B testing and personalization.

Phase 5:

- Scale hardening: cache candidate queries, async logs, idempotent locks, stress testing.


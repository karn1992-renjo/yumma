# Promotion Engine Enterprise Upgrade

See `admin-panel/docs/buy-x-get-y-enterprise-prd.md` for the complete Buy X Get Y product, engine, UI, API, database, validation, and analytics workflow.

## Architecture decision

The `promotions` table JSON columns remain the source of truth:

- `targets`
- `conditions`
- `rewards`
- `schedule`
- `stacking`
- `visibility`

The existing `promotion_targets`, `promotion_conditions`, and `promotion_rewards` tables are kept for backward database compatibility, but checkout evaluation reads from the JSON rule payload only. New feature work should avoid writing duplicate rule values into those row tables unless a future migration explicitly converts them into the source of truth.

## Implemented in this slice

- Added enterprise service layer around the existing engine facade:
  - `PromotionFinder`
  - `PromotionValidator`
  - `PromotionCalculator`
  - `PromotionStackService`
  - `CouponService`
  - `PromotionLogger`
  - `PromotionAnalytics`
  - `CampaignService`
  - `PromotionMigrationService`
  - `PromotionPreviewService`
- Kept `PromotionEngineService` as the public backend facade for backward compatibility.
- Expanded targeting and validation support:
  - restaurant, branch, zone
  - city, state, country, pincode
  - customer tags, user group, customer tier
  - device, platform, order type, payment method
  - contains/excludes item/category
  - min/max quantity, fees, distance, weight
  - coupon-required condition
- Expanded reward calculation support:
  - category, brand, combo, meal deal
  - BOGO, buy 2 get 1, buy 3 get 2
  - free item, free drink, free dessert
  - wallet cashback, cashback
  - reward points, scratch card, gift voucher, referral bonus
- Expanded stacking support:
  - exclusive
  - best offer
  - coupon + promotion
  - promotion + cashback
  - maximum promotions
  - priority ordering
- Added API support:
  - `GET /api/promotions/preview`
  - `GET /api/promotions/coupons/{code}`
  - existing promotion/coupon routes remain unchanged
- Expanded admin wizard type catalog and dynamic field behavior.
- Expanded analytics payload while keeping existing keys.

## Pending enterprise slices

- Add admin UI sections for advanced JSON rule editing with validation presets.
- Add CSV coupon import/export screens.
- Add campaign budget tracking and campaign-to-many-promotions management UI.
- Add checkout transaction usage budget locking for daily/weekly/monthly/campaign budgets.
- Add analytics charts for heatmap, campaign ROI, hourly/daily/monthly trends.
- Add Flutter screens for advanced reward display: points, gift voucher, scratch card, free item.
- Decide whether to drop unused target/condition/reward row tables in a future major migration after compatibility review.

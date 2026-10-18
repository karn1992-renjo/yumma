import google_mobile_ads
import GoogleMobileAds
import UIKit

/// Builds a NativeAdView matching the Android template
/// (android/.../ListTileNativeAdFactory.kt + res/layout/native_ad_card.xml)
/// programmatically -- no XIB needed. Registered under the factory id
/// "listTileNativeAd" in AppDelegate.swift, which must exactly match the
/// factoryId passed to Dart's NativeAd(...) calls (see
/// lib/widgets/common/native_ad_widget.dart). Every view that shows
/// ad-supplied content is assigned to the matching NativeAdView property
/// (headlineView, bodyView, ...) rather than wired to a manual tap
/// handler, so the Google Mobile Ads SDK itself owns click/impression
/// tracking, per AdMob's native ads implementation guide.
//
// Google Mobile Ads SDK 11+ dropped the GAD prefix from these Swift-only
// type names (GADNativeAd -> NativeAd, GADNativeAdView -> NativeAdView,
// GADMediaView -> MediaView); the Objective-C names keep the prefix for
// compatibility, but Swift call sites must use the unprefixed ones.
class ListTileNativeAdFactory: NSObject, FLTNativeAdFactory {
    func createNativeAd(
        _ nativeAd: NativeAd,
        customOptions: [AnyHashable: Any]? = nil
    ) -> NativeAdView? {
        let adView = NativeAdView()

        let card = UIView()
        card.backgroundColor = .white
        card.layer.cornerRadius = 20
        card.layer.borderWidth = 1
        card.layer.borderColor = UIColor(white: 0.945, alpha: 1).cgColor
        card.translatesAutoresizingMaskIntoConstraints = false
        adView.addSubview(card)
        NSLayoutConstraint.activate([
            card.leadingAnchor.constraint(equalTo: adView.leadingAnchor),
            card.trailingAnchor.constraint(equalTo: adView.trailingAnchor),
            card.topAnchor.constraint(equalTo: adView.topAnchor),
            card.bottomAnchor.constraint(equalTo: adView.bottomAnchor),
        ])

        let stack = UIStackView()
        stack.axis = .vertical
        stack.spacing = 10
        stack.translatesAutoresizingMaskIntoConstraints = false
        card.addSubview(stack)
        NSLayoutConstraint.activate([
            stack.leadingAnchor.constraint(equalTo: card.leadingAnchor, constant: 14),
            stack.trailingAnchor.constraint(equalTo: card.trailingAnchor, constant: -14),
            stack.topAnchor.constraint(equalTo: card.topAnchor, constant: 14),
            stack.bottomAnchor.constraint(equalTo: card.bottomAnchor, constant: -14),
        ])

        // "Ad" attribution badge -- required by AdMob policy on every
        // native ad, must stay visible regardless of ad content.
        let badge = UILabel()
        badge.text = "AD"
        badge.font = .boldSystemFont(ofSize: 10)
        badge.textColor = UIColor(red: 1, green: 0.353, blue: 0.122, alpha: 1)
        badge.backgroundColor = UIColor(red: 1, green: 0.945, blue: 0.909, alpha: 1)
        badge.textAlignment = .center
        badge.layer.cornerRadius = 4
        badge.layer.masksToBounds = true
        badge.translatesAutoresizingMaskIntoConstraints = false
        stack.addArrangedSubview(badge)
        NSLayoutConstraint.activate([
            badge.widthAnchor.constraint(equalToConstant: 26),
            badge.heightAnchor.constraint(equalToConstant: 16),
        ])

        let headerRow = UIStackView()
        headerRow.axis = .horizontal
        headerRow.spacing = 10
        headerRow.alignment = .center
        stack.addArrangedSubview(headerRow)

        let iconView = UIImageView()
        iconView.contentMode = .scaleAspectFill
        iconView.clipsToBounds = true
        iconView.layer.cornerRadius = 8
        iconView.translatesAutoresizingMaskIntoConstraints = false
        iconView.widthAnchor.constraint(equalToConstant: 44).isActive = true
        iconView.heightAnchor.constraint(equalToConstant: 44).isActive = true
        headerRow.addArrangedSubview(iconView)

        let titleColumn = UIStackView()
        titleColumn.axis = .vertical
        titleColumn.spacing = 2
        headerRow.addArrangedSubview(titleColumn)

        let headlineView = UILabel()
        headlineView.font = .boldSystemFont(ofSize: 15)
        headlineView.textColor = UIColor(white: 0.11, alpha: 1)
        headlineView.numberOfLines = 1
        titleColumn.addArrangedSubview(headlineView)

        let advertiserView = UILabel()
        advertiserView.font = .systemFont(ofSize: 12)
        advertiserView.textColor = UIColor(white: 0.41, alpha: 1)
        advertiserView.numberOfLines = 1
        titleColumn.addArrangedSubview(advertiserView)

        let mediaView = MediaView()
        mediaView.translatesAutoresizingMaskIntoConstraints = false
        mediaView.heightAnchor.constraint(equalToConstant: 160).isActive = true
        stack.addArrangedSubview(mediaView)

        let bodyView = UILabel()
        bodyView.font = .systemFont(ofSize: 13)
        bodyView.textColor = UIColor(white: 0.41, alpha: 1)
        bodyView.numberOfLines = 2
        stack.addArrangedSubview(bodyView)

        let ctaButton = UIButton(type: .system)
        ctaButton.backgroundColor = UIColor(red: 1, green: 0.353, blue: 0.122, alpha: 1)
        ctaButton.setTitleColor(.white, for: .normal)
        ctaButton.titleLabel?.font = .boldSystemFont(ofSize: 14)
        ctaButton.layer.cornerRadius = 14
        ctaButton.isUserInteractionEnabled = false // NativeAdView itself handles taps.
        ctaButton.translatesAutoresizingMaskIntoConstraints = false
        ctaButton.heightAnchor.constraint(equalToConstant: 44).isActive = true
        stack.addArrangedSubview(ctaButton)

        headlineView.text = nativeAd.headline
        adView.headlineView = headlineView

        if let body = nativeAd.body, !body.isEmpty {
            bodyView.text = body
            bodyView.isHidden = false
        } else {
            bodyView.isHidden = true
        }
        adView.bodyView = bodyView

        if let advertiser = nativeAd.advertiser, !advertiser.isEmpty {
            advertiserView.text = advertiser
            advertiserView.isHidden = false
        } else {
            advertiserView.isHidden = true
        }
        adView.advertiserView = advertiserView

        if let icon = nativeAd.icon {
            iconView.image = icon.image
            iconView.isHidden = false
        } else {
            iconView.isHidden = true
        }
        adView.iconView = iconView

        if let cta = nativeAd.callToAction, !cta.isEmpty {
            ctaButton.setTitle(cta, for: .normal)
            ctaButton.isHidden = false
        } else {
            ctaButton.isHidden = true
        }
        adView.callToActionView = ctaButton

        adView.mediaView = mediaView
        mediaView.mediaContent = nativeAd.mediaContent
        mediaView.isHidden = nativeAd.mediaContent.hasVideoContent == false
            && nativeAd.images?.isEmpty != false

        adView.nativeAd = nativeAd

        return adView
    }
}

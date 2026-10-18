package com.adgraph.yumma

import android.view.LayoutInflater
import android.view.View
import android.widget.Button
import android.widget.ImageView
import android.widget.TextView
import com.google.android.gms.ads.nativead.MediaView
import com.google.android.gms.ads.nativead.NativeAd
import com.google.android.gms.ads.nativead.NativeAdView
import io.flutter.plugins.googlemobileads.NativeAdFactory

/**
 * Inflates res/layout/native_ad_card.xml and binds a loaded NativeAd to it.
 * Registered under the factory id "listTileNativeAd" in MainActivity, which
 * must exactly match the factoryId passed to Dart's NativeAd(...) calls
 * (see lib/widgets/common/native_ad_widget.dart) -- Flutter looks the
 * factory up by that string at ad-load time.
 *
 * NativeAdFactory is a top-level interface
 * (io.flutter.plugins.googlemobileads.NativeAdFactory), NOT nested inside
 * GoogleMobileAdsPlugin -- GoogleMobileAdsPlugin.NativeAdFactory does not
 * exist and fails to compile ("Unresolved reference").
 */
class ListTileNativeAdFactory(private val layoutInflater: LayoutInflater) :
    NativeAdFactory {

    override fun createNativeAd(
        nativeAd: NativeAd,
        customOptions: MutableMap<String, Any>?
    ): NativeAdView {
        val adView = layoutInflater.inflate(
            R.layout.native_ad_card,
            null
        ) as NativeAdView

        val headlineView = adView.findViewById<TextView>(R.id.ad_headline)
        val advertiserView = adView.findViewById<TextView>(R.id.ad_advertiser)
        val bodyView = adView.findViewById<TextView>(R.id.ad_body)
        val iconView = adView.findViewById<ImageView>(R.id.ad_icon)
        val mediaView = adView.findViewById<MediaView>(R.id.ad_media)
        val ctaView = adView.findViewById<Button>(R.id.ad_call_to_action)

        headlineView.text = nativeAd.headline
        adView.headlineView = headlineView

        if (nativeAd.body.isNullOrEmpty()) {
            bodyView.visibility = View.GONE
        } else {
            bodyView.visibility = View.VISIBLE
            bodyView.text = nativeAd.body
        }
        adView.bodyView = bodyView

        if (nativeAd.advertiser.isNullOrEmpty()) {
            advertiserView.visibility = View.GONE
        } else {
            advertiserView.visibility = View.VISIBLE
            advertiserView.text = nativeAd.advertiser
        }
        adView.advertiserView = advertiserView

        val icon = nativeAd.icon
        if (icon == null) {
            iconView.visibility = View.GONE
        } else {
            iconView.visibility = View.VISIBLE
            iconView.setImageDrawable(icon.drawable)
        }
        adView.iconView = iconView

        if (nativeAd.callToAction.isNullOrEmpty()) {
            ctaView.visibility = View.GONE
        } else {
            ctaView.visibility = View.VISIBLE
            ctaView.text = nativeAd.callToAction
        }
        adView.callToActionView = ctaView

        adView.mediaView = mediaView
        nativeAd.mediaContent?.let { mediaView.mediaContent = it }
        mediaView.visibility =
            if (nativeAd.mediaContent != null) View.VISIBLE else View.GONE

        adView.setNativeAd(nativeAd)
        return adView
    }
}

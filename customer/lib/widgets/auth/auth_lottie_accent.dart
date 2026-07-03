import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';

class AuthLottieAccent extends StatelessWidget {
  final double height;

  const AuthLottieAccent({
    super.key,
    this.height = 120,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height * 1.25,
      child: Lottie.network(
        'https://assets2.lottiefiles.com/packages/lf20_jpk8l4d3.json',
        repeat: true,
        fit: BoxFit.contain,
        frameRate: FrameRate.max,
        errorBuilder: (context, error, stackTrace) => Container(
          decoration: BoxDecoration(
            color: const Color(0xFFFFF1EA),
            borderRadius: BorderRadius.circular(28),
          ),
          alignment: Alignment.center,
          child: const Icon(
            Icons.local_pizza_rounded,
            color: Color(0xFFFF5A1F),
            size: 40,
          ),
        ),
      ),
    );
  }
}

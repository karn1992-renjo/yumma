import 'dart:async';

import 'package:flutter/material.dart';

class LoadingButton extends StatelessWidget {
  final FutureOr<void> Function()? onPressed;
  final bool isLoading;
  final String text;
  final Color? color;
  final Color? backgroundColor;
  final Color? textColor;
  final double? width;

  const LoadingButton({
    Key? key,
    required this.onPressed,
    required this.isLoading,
    required this.text,
    this.color,
    this.backgroundColor,
    this.textColor,
    this.width,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: width ?? double.infinity,
      child: ElevatedButton(
        onPressed: isLoading || onPressed == null ? null : () => onPressed!.call(),
        style: ElevatedButton.styleFrom(
          backgroundColor: backgroundColor ?? color ?? const Color(0xFF0E9F6E),
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: isLoading
            ? const SizedBox(
                height: 20,
                width: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : Text(
                text,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: textColor,
                ),
              ),
      ),
    );
  }
}

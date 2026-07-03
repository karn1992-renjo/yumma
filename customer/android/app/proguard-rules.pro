# SLF4J checks for this optional binding class at runtime and falls back to a
# no-op logger when no binding is packaged. R8 needs the warning suppressed.
-dontwarn org.slf4j.impl.StaticLoggerBinder

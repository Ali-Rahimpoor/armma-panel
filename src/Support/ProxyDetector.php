<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Support;

/**
 * Heuristic CDN / reverse-proxy detection.
 *
 * The OTP layer throttles by client IP. When the site sits behind a CDN or
 * reverse proxy WITHOUT the "trusted proxy" toggle, every visitor shares the
 * proxy's address — one abuser locks the OTP flow for the whole site. This
 * detector is the heuristic that powers the admin warning for exactly that
 * situation.
 */
class ProxyDetector {

    /**
     * Does the current request LOOK like it arrives through a CDN / proxy?
     * True when a proxy-revealing header is present, or when the directly
     * connected peer (REMOTE_ADDR) is a private address (a local reverse
     * proxy in front of PHP-FPM / the web server).
     *
     * @param array<string, mixed>|null $server
     */
    public static function isBehindProxy(?array $server = null): bool {
        $server = $server ?? $_SERVER;

        return self::hasProxyHeaders($server) || self::remoteAddrIsPrivate($server);
    }

    /**
     * Client-IP headers that only a proxy would set.
     *
     * @param array<string, mixed> $server
     */
    public static function hasProxyHeaders(array $server): bool {
        return isset($server['HTTP_CF_CONNECTING_IP'])
            || isset($server['HTTP_X_FORWARDED_FOR'])
            || isset($server['HTTP_X_REAL_IP']);
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function remoteAddrIsPrivate(array $server): bool {
        $ip = trim((string) ($server['REMOTE_ADDR'] ?? ''));

        if ($ip === '') {
            return false;
        }

        return self::isPrivateIp($ip);
    }

    /**
     * Private / reserved IP ranges: RFC 1918, loopback, link-local, ULA and
     * CGNAT. A public site reachable through the open internet never has
     * such an address as its direct peer — seeing one means a proxy sits in
     * front of PHP.
     */
    public static function isPrivateIp(string $ip): bool {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            foreach (['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8', '169.254.0.0/16', '100.64.0.0/10'] as $range) {
                if (self::ipv4InRange($ip, $range)) {
                    return true;
                }
            }

            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $ip = strtolower(trim($ip));

            if ($ip === '::1') {
                return true; // loopback
            }

            // Unique-local fc00::/7 and link-local fe80::/10.
            foreach (['fc', 'fd', 'fe8', 'fe9', 'fea', 'feb'] as $prefix) {
                if (str_starts_with($ip, $prefix)) {
                    return true;
                }
            }

            return false;
        }

        return false; // not an IP at all
    }

    /**
     * @param string $ip
     * @param string $range "subnet/bits", e.g. "172.16.0.0/12"
     */
    private static function ipv4InRange(string $ip, string $range): bool {
        [$subnet, $bits] = array_pad(explode('/', $range, 2), 2, '32');
        $bits       = (int) $bits;
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : ((-1 << (32 - $bits)) & 0xFFFFFFFF);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * The exact admin-facing warning text (single source of truth — used by
     * both the settings screen and the admin notice).
     */
    public static function warningMessage(): string {
        return __(
            'به نظر می‌رسد سایت شما پشت CDN/پروکسی است؛ برای اینکه محدودیت IP برای همه کاربران یکی نشود، گزینه ‹سایت پشت پروکسی مورد اعتماد است› را فعال کنید.',
            'arma-panel'
        );
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class AllowIpMiddleware
{
    protected $allow_ips;
    protected $allow_ip_ranges;
    public function __construct()
    {
        $this->allow_ips = config('app.allow_ips');
        $this->allow_ip_ranges = config('app.allow_ip_ranges');
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info(json_encode($request->getClientIps()));
        foreach ($request->getClientIps() as $ip) {
            if (! $this->isValidIp($ip) && ! $this->isValidIpRange($ip)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
        }

        return $next($request);
    }

    /**
     * Check if the given IP is valid.
     *
     * @param $ip
     * @return bool
     */
    protected function isValidIp($ip): bool
    {
        if (empty($this->allow_ips)) {
            return true;
        }
        $ips = explode(',', $this->allow_ips);
        return in_array($ip, $ips);
    }


    /**
     * Check if the ip is in the given IP-range.
     *
     * @param $ip
     * @return bool
     */
    protected function isValidIpRange($ip): bool
    {
        if (empty($this->allow_ip_ranges)) {
            return true;
        }
        $this->allow_ip_ranges = explode(',',$this->allow_ip_ranges);
        return IpUtils::checkIp($ip, $this->allow_ip_ranges);
    }
}

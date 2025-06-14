<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OTPHP\TOTP;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorController extends Controller
{

    public function generateSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = User::where('email', $request->email)->first();

        if ($user->hasRole("jobseeker")) {
            return response()->json([
                'error' => true,
                'message' => 'User not found'
            ], 404);
        }

        // Generate a unique token
        $token = Str::random(20);

        // Generate a 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create a new 2FA session
        TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => $token,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send the code via email
        $this->sendTwoFactorCode($user->email, $code);

        // Generate verification URL
        $verificationUrl = ("https://applymandu.vercel.app/verify-2fa/{$token}");

        // Generate the otpauth URL for authenticator apps
        $issuer = "Applymandu";
        $account = $user->email;
        $secret = $user->secret_key; // Make sure this is a valid TOTP secret (base32 encoded)

        $otpAuthUrl = "otpauth://totp/" .
            rawurlencode("$issuer:$account") .
            "?secret=$secret" .
            "&issuer=" . rawurlencode($issuer) .
            "&algorithm=SHA1&digits=6&period=30";

        // Generate QR code from the otpauth URL
        $qrCode = base64_encode(QrCode::format('png')->size(200)->generate($otpAuthUrl));

        return response()->json([
            'qr_code' => 'data:image/png;base64,' . $qrCode,
            'verification_url' => $verificationUrl,
            'token' => $token,
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            'secret_key' => $user->secret_key, 
            'email' => $user->email,
        ]);
    }


    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the session by token
        $session = TwoFactorSession::where('token', $request->token)
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$session) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid or expired token'
            ], 400);
        }
        
        $user = User::find($session->user_id);
        
        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'User not found'
            ], 404);
        }
        

        if ($session->code === $request->code) {
            $session->update(['is_verified' => true]);
            
            return response()->json([
                'error' => false,
                'message' => 'Two-factor authentication successful',
                 'user' => $user,
                'token'=> $user->createToken($user->email)->accessToken,
            ]);
        }
        

        if ($user->secret_key) {
            $totp = TOTP::create($user->secret_key);
            
            if ($totp->verify($request->code, null, 1)) {
                // Mark session as verified
                $session->update(['is_verified' => true]);
                
                return response()->json([
                    'error' => false,
                    'message' => 'Two-factor authentication successful',
                    'user' => $user,
                    'token'=> $user->createToken($user->email)->accessToken,
                ]);
            }
        }
        
        return response()->json([
            'error' => true,
            'message' => 'Invalid verification code'
        ], 400);
    }


    private function sendTwoFactorCode($email, $code)
    {
        // You can use a dedicated mail class, but for simplicity:
        Mail::send('two-factor-code', ['code' => $code], function ($message) use ($email) {
            $message->to($email)
                ->subject('Your Two-Factor Authentication Code');
        });
    }
}

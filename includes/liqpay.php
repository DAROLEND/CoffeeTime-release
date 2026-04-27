<?php
/**
 * LiqPay PHP SDK (official, v3)
 * https://www.liqpay.ua/documentation/uk
 */
class LiqPay {

    private $public_key;
    private $private_key;

    public function __construct(string $public_key, string $private_key) {
        $this->public_key  = $public_key;
        $this->private_key = $private_key;
    }

    /** Generate a ready-to-submit HTML payment form */
    public function cnb_form(array $params): string {
        $data      = $this->cnb_data($params);
        $signature = $this->cnb_signature($data);
        return sprintf(
            '<form method="POST" action="https://www.liqpay.ua/api/3/checkout"
                  accept-charset="utf-8" id="liqpayForm">
               <input type="hidden" name="data"      value="%s"/>
               <input type="hidden" name="signature" value="%s"/>
             </form>',
            htmlspecialchars($data,      ENT_QUOTES),
            htmlspecialchars($signature, ENT_QUOTES)
        );
    }

    /** Base64-encode the JSON payload */
    public function cnb_data(array $params): string {
        $params['public_key'] = $this->public_key;
        if (empty($params['version']))  $params['version']  = 3;
        if (empty($params['currency'])) $params['currency'] = 'UAH';
        return base64_encode(json_encode($params));
    }

    /** HMAC-SHA1 signature */
    public function cnb_signature(string $data): string {
        return $this->str_to_sign($this->private_key . $data . $this->private_key);
    }

    public function str_to_sign(string $str): string {
        return base64_encode(sha1($str, true));
    }

    /** Decode LiqPay callback data */
    public function decode_data_str(string $data): object {
        return (object) json_decode(base64_decode($data));
    }

    /** Verify callback signature */
    public function verify_signature(string $data, string $signature): bool {
        return $this->cnb_signature($data) === $signature;
    }
}

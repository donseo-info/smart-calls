<?php
/**
 * Отправка офлайн-конверсий в Яндекс.Метрику через Offline Conversions API
 */
class MetrikaSender
{
    private string $accessToken;
    private string $apiUrl = 'https://api-metrica.yandex.net/management/v1/counter/{counterId}/offline_conversions/upload';

    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @param string      $counterId  ID счётчика Метрики
     * @param string      $goalName   Название цели
     * @param int         $timestamp  Unix timestamp события
     * @param string|null $clientId   ClientID из cookie _ym_uid (необязательно)
     * @param string|null $phone      Телефон звонящего (необязательно)
     * @return array{success:bool, http_code:int, csv:string, error:string}
     */
    public function send(string $counterId, string $goalName, int $timestamp, ?string $clientId = null, ?string $phone = null): array
    {
        $headers = [];
        $values  = [];

        if ($clientId) {
            $headers[] = 'ClientId';
            $values[]  = $clientId;
        }

        if ($phone) {
            $headers[] = 'phones';
            $values[]  = preg_replace('/\D/', '', $phone);
        }

        $headers[] = 'Target';
        $values[]  = $goalName;
        $headers[] = 'DateTime';
        $values[]  = $timestamp;

        $csv = implode(',', $headers) . "\n" . implode(',', $values) . "\n";

        // Временный файл для multipart/form-data
        $tmpPath = tempnam(sys_get_temp_dir(), 'scw_') . '.csv';
        file_put_contents($tmpPath, $csv);

        $url = str_replace('{counterId}', $counterId, $this->apiUrl);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => new CURLFile($tmpPath, 'text/csv', 'conversions.csv')],
            CURLOPT_HTTPHEADER     => ['Authorization: OAuth ' . $this->accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        @unlink($tmpPath);

        return [
            'success'   => $httpCode === 200,
            'http_code' => $httpCode,
            'csv'       => $csv,
            'response'  => $response,
            'error'     => $error,
        ];
    }
}

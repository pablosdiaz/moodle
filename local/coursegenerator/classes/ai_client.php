<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_coursegenerator;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin client for the Google Gemini (Generative Language API) generateContent endpoint.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_client {

    /** @var string Base URL of the Gemini Generative Language API. */
    const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** @var string */
    protected string $apikey;

    /** @var string */
    protected string $model;

    /**
     * Build a client from the plugin configuration.
     *
     * @return self
     */
    public static function from_config(): self {
        $apikey = (string) get_config('local_coursegenerator', 'apikey');
        $model = (string) get_config('local_coursegenerator', 'model');

        return new self($apikey, $model ?: 'gemini-2.0-flash');
    }

    /**
     * Whether the plugin has enough configuration to call the AI provider.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $apikey = (string) get_config('local_coursegenerator', 'apikey');
        return $apikey !== '';
    }

    /**
     * @param string $apikey
     * @param string $model
     */
    public function __construct(string $apikey, string $model) {
        $this->apikey = $apikey;
        $this->model = $model;
    }

    /**
     * Returns the model identifier this client is configured with.
     *
     * @return string
     */
    public function get_model(): string {
        return $this->model;
    }

    /**
     * Ask Gemini to produce JSON data matching the given instructions and decode the result.
     *
     * @param string $systemprompt instructions describing the required JSON shape.
     * @param string $userprompt the actual request (topic, counts, etc).
     * @return array decoded JSON response.
     * @throws \moodle_exception
     */
    public function generate_json(string $systemprompt, string $userprompt): array {
        if (empty($this->apikey)) {
            throw new \moodle_exception('form_noapikey', 'local_coursegenerator');
        }

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userprompt]]],
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemprompt]],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json',
            ],
        ];

        $endpoint = self::BASE_URL . rawurlencode($this->model) . ':generateContent?key=' . rawurlencode($this->apikey);

        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $options = [
            'CURLOPT_TIMEOUT' => 90,
            'CURLOPT_CONNECTTIMEOUT' => 15,
        ];

        $response = $curl->post($endpoint, json_encode($payload), $options);
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);

        if ($httpcode < 200 || $httpcode >= 300) {
            throw new \moodle_exception('error_aihttp', 'local_coursegenerator', '', $httpcode);
        }

        $decoded = json_decode((string) $response, true);
        $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($content === null) {
            throw new \moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        return $data;
    }
}

<?php
if (!defined('ABSPATH')) exit;

class PFAI_AI_Service {
    public static function get_api_key() {
        if (defined('PFAI_OPENAI_API_KEY') && PFAI_OPENAI_API_KEY) {
            return trim((string) PFAI_OPENAI_API_KEY);
        }
        return trim((string) get_option('pfai_openai_api_key', ''));
    }

    public static function is_configured() {
        return self::get_api_key() !== '';
    }

    public static function generate_participant_summary($participant_name, array $context) {
        $api_key = self::get_api_key();
        if ($api_key === '') {
            return new WP_Error('pfai_ai_not_configured', 'OpenAI is not configured. Add an API key under PFS Mission Control → Settings.');
        }

        $model = trim((string) get_option('pfai_openai_model', 'gpt-5-mini'));
        if ($model === '') $model = 'gpt-5-mini';

        $safe_context = array(
            'participant_name' => sanitize_text_field($participant_name),
            'program' => sanitize_text_field($context['program'] ?? ''),
            'status' => sanitize_text_field($context['status'] ?? ''),
            'career_goal' => sanitize_textarea_field($context['career_goal'] ?? ''),
            'employment_status' => sanitize_text_field($context['employment_status'] ?? ''),
            'profile_completion' => absint($context['profile_completion'] ?? 0),
            'open_tasks' => absint($context['open_tasks'] ?? 0),
            'follow_up' => sanitize_text_field($context['follow_up'] ?? ''),
            'recent_notes' => array_map('sanitize_textarea_field', array_slice((array)($context['recent_notes'] ?? array()), 0, 5)),
            'document_names' => array_map('sanitize_text_field', array_slice((array)($context['document_names'] ?? array()), 0, 15)),
        );

        $instructions = 'You are the Pathway Forward Solutions workforce-development assistant. Write a concise, professional case-management summary for staff. Use only the supplied participant data. Do not invent facts. Return plain text with exactly these headings: SUMMARY, STRENGTHS, GAPS, NEXT 3 ACTIONS, RISK FLAGS. Keep the total under 250 words. Do not diagnose medical or mental-health conditions.';

        $body = array(
            'model' => $model,
            'instructions' => $instructions,
            'input' => wp_json_encode($safe_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'store' => false,
        );

        return self::request_text_response($body);
    }

    public static function generate_navigator_response(array $conversation_messages, array $context = array()) {
        $service_context = sanitize_key($context['service_context'] ?? 'general');
        $participant_goal = sanitize_textarea_field($context['participant_goal'] ?? '');

        $instructions = "You are the Pathway Forward AI Service Navigator for workforce-development services. "
            . "Never provide medical, legal, financial, eligibility, hiring, or emergency decisions. "
            . "If an emergency risk appears, advise immediate local emergency services and provide a brief safety message. "
            . "Never claim to contact support automatically. "
            . "Do not perform record deletion, payment processing, or user-permission changes. "
            . "Keep responses practical, supportive, and concise. "
            . "For the resume-interview context, first ask whether the participant needs help with resume, cover letter, job application, or interview preparation, then gather only relevant details before guidance. "
            . "Offer clear next steps and remind the participant they can contact support any time.";

        $input = array(
            'service_context' => $service_context,
            'participant_goal' => $participant_goal,
            'messages' => array(),
        );

        foreach (array_slice($conversation_messages, -12) as $message) {
            $role = sanitize_key($message['role'] ?? 'user');
            $content = sanitize_textarea_field($message['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $input['messages'][] = array(
                'role' => in_array($role, array('assistant', 'system'), true) ? $role : 'user',
                'content' => $content,
            );
        }

        $body = array(
            'model' => trim((string) get_option('pfai_openai_model', 'gpt-5-mini')) ?: 'gpt-5-mini',
            'instructions' => $instructions,
            'input' => wp_json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'store' => false,
        );

        return self::request_text_response($body);
    }

    private static function request_text_response(array $body) {
        $api_key = self::get_api_key();
        if ($api_key === '') {
            return new WP_Error('pfai_ai_not_configured', 'OpenAI is not configured. Add an API key under PFS Mission Control -> Settings.');
        }

        $response = wp_remote_post('https://api.openai.com/v1/responses', array(
            'timeout' => 45,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $message = isset($decoded['error']['message']) ? sanitize_text_field($decoded['error']['message']) : 'The AI request failed.';
            return new WP_Error('pfai_ai_request_failed', $message);
        }

        if (!empty($decoded['output_text']) && is_string($decoded['output_text'])) {
            return trim($decoded['output_text']);
        }

        $parts = array();
        foreach ((array)($decoded['output'] ?? array()) as $output_item) {
            foreach ((array)($output_item['content'] ?? array()) as $content_item) {
                if (($content_item['type'] ?? '') === 'output_text' && !empty($content_item['text'])) {
                    $parts[] = $content_item['text'];
                }
            }
        }
        $text = trim(implode("\n", $parts));
        if ($text === '') {
            return new WP_Error('pfai_ai_empty_response', 'The AI service returned an empty response.');
        }
        return $text;
    }
}

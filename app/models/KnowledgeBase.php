<?php
// FILE: /app/models/KnowledgeBase.php

/**
 * KnowledgeBase Model - FAQ entries and uploaded documents used by the AI engine.
 * Entries with bot_id NULL are shared by all bots of the tenant.
 */
class KnowledgeBase extends Model {

    const STOPWORDS = ['the', 'and', 'for', 'are', 'you', 'your', 'what', 'how', 'can', 'does', 'with', 'this', 'that', 'have', 'from', 'about', 'is', 'do', 'i', 'me', 'my', 'a', 'an', 'of', 'to', 'in', 'on', 'it'];

    protected $table = 'knowledge_base';
    protected $searchable = ['topic', 'question', 'keywords', 'category'];

    public function listWithBot($tenantId, $filters, $search, $limit, $offset) {
        list($where, $params) = $this->buildWhere($filters, $tenantId, $search, 'kb');
        $sql = "SELECT kb.id, kb.tenant_id, kb.bot_id, kb.topic, kb.question, LEFT(kb.answer, 300) AS answer,
                       kb.keywords, kb.category, kb.file_path, kb.status, kb.created_at, b.name AS bot_name
                FROM {$this->table} kb
                LEFT JOIN bots b ON b.id = kb.bot_id
                WHERE {$where}
                ORDER BY kb.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Find the best matching entries for a question.
     * 1) MySQL FULLTEXT relevance, 2) fallback: keyword overlap scoring.
     */
    public function search($query, $tenantId, $botId = null, $limit = 3) {
        $query = trim((string) $query);
        if ($query === '') {
            return [];
        }

        $scope = "tenant_id = ? AND status = 'active'";
        $scopeParams = [$tenantId];
        if ($botId !== null) {
            $scope .= " AND (bot_id = ? OR bot_id IS NULL)";
            $scopeParams[] = $botId;
        }

        $sql = "SELECT *, MATCH(question, answer, keywords) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
                FROM {$this->table}
                WHERE {$scope} AND MATCH(question, answer, keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC " . $this->limitClause($limit);
        $results = $this->query($sql, array_merge([$query], $scopeParams, [$query]))->fetchAll();
        if (!empty($results)) {
            return $results;
        }

        return $this->keywordSearch($query, $scope, $scopeParams, $limit);
    }

    private function keywordSearch($query, $scope, $scopeParams, $limit) {
        $terms = self::terms($query);
        if (empty($terms)) {
            return [];
        }

        $entries = $this->query(
            "SELECT * FROM {$this->table} WHERE {$scope} ORDER BY id DESC LIMIT 500",
            $scopeParams
        )->fetchAll();

        $scored = [];
        foreach ($entries as $entry) {
            $keywords = array_map('trim', explode(',', Flow::normalize($entry['keywords'])));
            $haystack = Flow::normalize($entry['topic'] . ' ' . $entry['question']);
            $score = 0;
            foreach ($terms as $term) {
                if (in_array($term, $keywords, true)) {
                    $score += 3;
                } elseif (preg_match('/\b' . preg_quote($term, '/') . '/u', $haystack)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $entry['relevance'] = $score;
                $scored[] = $entry;
            }
        }

        usort($scored, function ($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });
        return array_slice($scored, 0, $limit);
    }

    public static function terms($text) {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Flow::normalize($text), -1, PREG_SPLIT_NO_EMPTY);
        $terms = [];
        foreach ($words as $word) {
            if (strlen($word) >= 3 && !in_array($word, self::STOPWORDS, true)) {
                $terms[] = $word;
            }
        }
        return array_values(array_unique($terms));
    }

    public function storageBytes($tenantId) {
        $stmt = $this->query("SELECT COALESCE(SUM(file_size), 0) AS b FROM {$this->table} WHERE tenant_id = ?", [$tenantId]);
        return (int) $stmt->fetch()['b'];
    }
}

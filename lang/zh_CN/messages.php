<?php

declare(strict_types=1);

/*
 * Chinese (Mandarin, Simplified). Reviewed by a non-native speaker; corrections by native speakers are welcome.
 * Polite "您"; Chinese has no plural forms, so texts with a count have one form.
 */
return [
    'plugin_name' => 'HTML 翻译器',
    'status' => [
        'missing' => '未翻译',
        'machine' => '草稿',
        'reviewed' => '已审核',
        'stale' => '待更新',
    ],
    'actions' => [
        'translate' => [
            'label' => '生成翻译草稿',
            'heading' => '根据源文本生成草稿',
            'description' => '机器翻译生成的是草稿，请在发布前审核。',
            'locale' => '目标语言',
            'policy' => '翻译范围',
            'policies' => [
                'only_missing' => '仅翻译尚未翻译的内容',
                'overwrite_machine' => '同时覆盖翻译草稿',
                'force' => '同时覆盖已审核的翻译',
            ],
            'confirm_force' => '我确认将覆盖已审核的翻译。',
        ],
        'review' => [
            'label' => '标记为已审核',
            'heading' => '将翻译草稿标记为已审核？',
            'done' => '已将 :count 条翻译标记为已审核。',
            'stale_warning' => '其中 :count 条译文的源文本已更改。请逐条与当前源文本核对，再标记为已审核。',
            'done_stale' => '其中 :count 条译文在审核前已不是最新版本。',
        ],
        'field' => [
            'label' => '翻译',
        ],
        'bulk' => [
            'label' => '批量生成翻译草稿',
        ],
    ],
    'tooltips' => [
        'stale' => '上次翻译后，源文本已更改。',
    ],
    'columns' => [
        'status' => '翻译',
    ],
    'notifications' => [
        'field_translated' => '已插入翻译草稿',
        'field_translated_body' => '请审核草稿并保存表单。',
        'field_not_translated' => '无法翻译此字段',
        'started' => '翻译已开始',
        'started_body' => '草稿完成后将通知您。',
        'failed' => '翻译未能完成',
        'failed_body' => '请稍后重试。如果问题仍然存在，请联系管理员。',
        'batch_done' => '翻译草稿已生成',
        'batch_body' => '已翻译记录：:done / :total',
        'translated' => '翻译草稿已生成',
        'translated_with_problems' => '翻译过程中出现问题',
        'counts' => '已翻译：:translated · 已跳过：:skipped · 不完整：:incomplete · 失败：:failed',
        'usage' => 'Token 数：:tokens · 缓存命中：:cache_hits',
        'issues' => '问题：',
    ],
];

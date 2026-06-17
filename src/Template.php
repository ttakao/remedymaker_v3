<?php
// Template.php

class Template {
    private $templateFile;
    private $vars = [];

    public function __construct($templateFile) {
        $this->templateFile = $templateFile;
    }

    /**
     * テンプレート変数の割り当て
     */
    public function assign($key, $value) {
        $this->vars[$key] = $value;
    }

    /**
     * レンダリング処理
     */
    public function render() {
        if (!file_exists($this->templateFile)) {
            return "テンプレートファイルが見つかりません: " . htmlspecialchars($this->templateFile);
        }
        
        $content = file_get_contents($this->templateFile);
        
        // {{変数名}} プレースホルダーを割り当てられた値に置換
        foreach ($this->vars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }
}
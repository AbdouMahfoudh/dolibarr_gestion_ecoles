<?php

class ButtonAbdou {
    // Types de boutons prédéfinis
    const TYPE_PRIMARY = 'primary';
    const TYPE_SECONDARY = 'secondary';
    const TYPE_SUCCESS = 'success';
    const TYPE_DANGER = 'danger';
    const TYPE_WARNING = 'warning';
    const TYPE_INFO = 'info';
    const TYPE_LIGHT = 'light';
    const TYPE_DARK = 'dark';
    const TYPE_LINK = 'link';
    const TYPE_GHOST = 'ghost';
    
    // Tailles
    const SIZE_SMALL = 'small';
    const SIZE_MEDIUM = 'medium';
    const SIZE_LARGE = 'large';
    
    private $label;
    private $type = self::TYPE_PRIMARY;
    private $size = self::TYPE_PRIMARY;
    private $class = '';
    private $id = null;
    private $name = null;
    private $value = null;
    private $icon = null;
    private $iconPosition = 'left';
    private $disabled = false;
    private $loading = false;
    private $fullWidth = false;
    private $outline = false;
    private $rounded = false;
    private $href = null;
    private $target = null;
    private $onclick = null;
    private $typeAttr = 'button'; // button, submit, reset
    private $attributes = [];
    private static $assetsLoaded = false;

    public function __construct($label, $type = self::TYPE_PRIMARY, $size = self::TYPE_PRIMARY) {
        $this->label = $this->cleanLabel($label);  // ← Décodage automatique
    
        $this->type = $type;
        $this->size = $size;
    }
    private function cleanForJS($string) {
        if (empty($string)) return '';
        // Décoder les entités HTML
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
        // Échapper les guillemets
        $string = addslashes($string);
        return $string;
    }
    private function cleanLabel($string) {
        if (empty($string)) return '';
        // Décoder les entités HTML
        return html_entity_decode($string, ENT_QUOTES, 'UTF-8');
    }

    // Méthodes de configuration
    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    public function setSize($size) {
        $this->size = $size;
        return $this;
    }

    public function setClass($class) {
        $this->class = $class;
        return $this;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function setValue($value) {
        $this->value = $value;
        return $this;
    }

    public function setIcon($icon, $position = 'left') {
        $this->icon = $icon;
        $this->iconPosition = $position;
        return $this;
    }

    public function setDisabled($disabled = true) {
        $this->disabled = $disabled;
        return $this;
    }

    public function setLoading($loading = true) {
        $this->loading = $loading;
        return $this;
    }

    public function setFullWidth($fullWidth = true) {
        $this->fullWidth = $fullWidth;
        return $this;
    }

    public function setOutline($outline = true) {
        $this->outline = $outline;
        return $this;
    }

    public function setRounded($rounded = true) {
        $this->rounded = $rounded;
        return $this;
    }

    public function setHref($href, $target = null) {
        $this->href = $href;
        $this->target = $target;
        return $this;
    }

    public function setOnclick($onclick) {
        $this->onclick = $onclick;
        return $this;
    }

    public function setTypeAttr($type) {
        $this->typeAttr = $type;
        return $this;
    }

    public function setAttribute($key, $value) {
        $this->attributes[$key] = $value;
        return $this;
    }

    private function renderAssets() {
        if (self::$assetsLoaded) return '';
        self::$assetsLoaded = true;

        $css = '
        <style>
            
        </style>';

        return $css;
    }

    public function render() {
        $assets = $this->renderAssets();

        $classes = ['dolibarr-btn', $this->type, $this->size];
        
        if ($this->class) {
            $classes[] = $this->class;
        }
        if ($this->fullWidth) {
            $classes[] = 'full-width';
        }
        if ($this->outline) {
            $classes[] = 'outline';
        }
        if ($this->rounded) {
            $classes[] = 'rounded';
        }
        if ($this->loading) {
            $classes[] = 'loading';
        }
        if ($this->icon && empty($this->label)) {
            $classes[] = 'icon-only';
        }

        $classAttr = implode(' ', $classes);

        $attrs = [];
        $attrs[] = 'class="' . htmlspecialchars($classAttr) . '"';
        
        if ($this->id) {
            $attrs[] = 'id="' . htmlspecialchars($this->id) . '"';
        }
        if ($this->name) {
            $attrs[] = 'name="' . htmlspecialchars($this->name) . '"';
        }
        if ($this->value) {
            $attrs[] = 'value="' . htmlspecialchars($this->value) . '"';
        }
        if ($this->disabled) {
            $attrs[] = 'disabled';
        }
        if ($this->onclick) {
            $attrs[] = 'onclick="' . $this->cleanForJS($this->onclick) . '"';
        }
        if ($this->target) {
            $attrs[] = 'target="' . htmlspecialchars($this->target) . '"';
        }

        // Attributs personnalisés
        foreach ($this->attributes as $key => $value) {
            $attrs[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        $attrsStr = implode(' ', $attrs);

        // Construction du contenu
        $content = '';
        
        if ($this->icon && $this->iconPosition === 'left') {
            $content .= '<span class="btn-icon left">' . htmlspecialchars($this->icon) . '</span>';
        }
        
        if ($this->label) {
            $content .= '<span class="btn-label">' . htmlspecialchars($this->label) . '</span>';
        }
        
        if ($this->icon && $this->iconPosition === 'right') {
            $content .= '<span class="btn-icon right">' . htmlspecialchars($this->icon) . '</span>';
        }

        // Si c'est un lien
        if ($this->href) {
            return $assets . '<a href="' . htmlspecialchars($this->href) . '" ' . $attrsStr . '>' . $content . '</a>';
        }
        

        // Sinon c'est un bouton
        return $assets . '<button type="' . htmlspecialchars($this->typeAttr) . '" ' . $attrsStr . '>' . $content . '</button>';
    }

    public function __toString() {
        return $this->render();
    }

    // ============================================
    // MÉTHODES STATIQUES POUR CRÉATION RAPIDE
    // ============================================

    public static function primary($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_PRIMARY, $size);
    }

    public static function secondary($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_SECONDARY, $size);
    }

    public static function success($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_SUCCESS, $size);
    }

    public static function danger($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_DANGER, $size);
    }

    public static function warning($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_WARNING, $size);
    }

    public static function info($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_INFO, $size);
    }

    public static function light($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_LIGHT, $size);
    }

    public static function dark($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_DARK, $size);
    }

    public static function link($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_LINK, $size);
    }

    public static function ghost($label, $size = self::SIZE_MEDIUM) {
        return new self($label, self::TYPE_GHOST, $size);
    }

    // ============================================
    // MÉTHODE POUR CRÉER UN GROUPE DE BOUTONS
    // ============================================

    public static function group($buttons, $class = '') {
        $html = '<div class="dolibarr-btn-group' . ($class ? ' ' . htmlspecialchars($class) : '') . '">';
        foreach ($buttons as $button) {
            $html .= $button->render();
        }
        $html .= '</div>';
        return $html;
    }
}
?>
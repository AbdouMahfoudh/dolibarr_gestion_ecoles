<?php

class SelectDolibarr {
    private $name;
    private $id;
    private $options = [];
    private $selected = [];
    private $placeholder = "Sélectionner...";
    private $class = "";
    private $required = false;
    private $disabled = false;
    private $width = "100%";
    private $maxHeight = "250px";
    private $icon = null;
    private $allowClear = false;
    private $searchable = false;
    private $multiple = false;
    private $maxSelected = null;
    private static $assetsLoaded = false;

    public function __construct($name, $id = null) {
        $this->name = $name;
        $this->id = $id ?? $name;
    }

    public function ajouterOption($value, $label, $selected = false, $group = null) {
        $this->options[] = [
            'value' => $value,
            'label' => $label,
            'selected' => $selected,
            'group' => $group
        ];
        if ($selected) {
            $this->selected[] = $value;
        }
        return $this;
    }

    public function ajouterOptions($optionsArray, $group = null) {
        foreach ($optionsArray as $value => $label) {
            $this->ajouterOption($value, $label, false, $group);
        }
        return $this;
    }

    public function ajouterGroupe($label, $options) {
        foreach ($options as $value => $optionLabel) {
            $this->ajouterOption($value, $optionLabel, false, $label);
        }
        return $this;
    }

    public function setPlaceholder($placeholder) {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function setClass($class) {
        $this->class = $class;
        return $this;
    }

    public function setRequired($required = true) {
        $this->required = $required;
        return $this;
    }

    public function setDisabled($disabled = true) {
        $this->disabled = $disabled;
        return $this;
    }

    public function setSelected($value) {
        if (is_array($value)) {
            $this->selected = $value;
        } else {
            $this->selected = [$value];
        }
        // Mettre à jour les options
        foreach ($this->options as &$option) {
            $option['selected'] = in_array($option['value'], $this->selected);
        }
        return $this;
    }

    public function setMultiple($multiple = true, $maxSelected = null) {
        $this->multiple = $multiple;
        $this->maxSelected = $maxSelected;
        return $this;
    }

    public function setWidth($width) {
        $this->width = $width;
        return $this;
    }

    public function setMaxHeight($height) {
        $this->maxHeight = $height;
        return $this;
    }

    public function setIcon($icon) {
        $this->icon = $icon;
        return $this;
    }

    public function setAllowClear($allowClear = true) {
        $this->allowClear = $allowClear;
        return $this;
    }

    public function setSearchable($searchable = true) {
        $this->searchable = $searchable;
        return $this;
    }

    private function renderAssets() {
        if (self::$assetsLoaded) return '';
        self::$assetsLoaded = true;

        $css = '
        <style>
            /* Reset et base */
            .dolibarr-select-wrapper {
                position: relative;
                display: inline-block;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-size: 14px;
            }

            /* Zone d\'affichage */
            .dolibarr-select-display {
                position: relative;
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 4px;
                min-height: 38px;
                padding: 4px 32px 4px 8px;
                background: #ffffff;
                border: 1px solid #d0d0d0;
                border-radius: 4px;
                cursor: pointer;
                user-select: none;
                transition: all 0.2s ease;
                color: #333333;
                line-height: 1.5;
            }

            .dolibarr-select-display:hover:not(.disabled) {
                border-color: #888888;
            }

            .dolibarr-select-display:focus,
            .dolibarr-select-display.active {
                border-color: #4a90d9;
                box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
                outline: none;
            }

            .dolibarr-select-display.disabled {
                background: #f5f5f5;
                color: #999999;
                cursor: not-allowed;
                opacity: 0.6;
            }

            /* Tags pour la sélection multiple */
            .dolibarr-select-tag {
                display: inline-flex;
                align-items: center;
                background: #e8f0fe;
                color: #1a73e8;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
                margin: 2px;
                gap: 4px;
                max-width: 150px;
            }

            .dolibarr-select-tag-text {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .dolibarr-select-tag-remove {
                cursor: pointer;
                font-weight: bold;
                color: #666;
                font-size: 14px;
                line-height: 1;
                padding: 0 2px;
            }

            .dolibarr-select-tag-remove:hover {
                color: #d32f2f;
            }

            /* Icone */
            .dolibarr-select-icon {
                margin-right: 8px;
                font-size: 16px;
                flex-shrink: 0;
            }

            /* Texte sélectionné */
            .dolibarr-select-text {
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                padding: 4px 0;
            }

            .dolibarr-select-text.placeholder {
                color: #999999;
            }

            /* Flèche */
            .dolibarr-select-arrow {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 10px;
                color: #666666;
                transition: transform 0.25s ease;
            }

            .dolibarr-select-display.active .dolibarr-select-arrow {
                transform: translateY(-50%) rotate(180deg);
            }

            /* Bouton clear */
            .dolibarr-select-clear {
                position: absolute;
                right: 28px;
                top: 50%;
                transform: translateY(-50%);
                width: 16px;
                height: 16px;
                display: none;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                color: #999999;
                cursor: pointer;
                border-radius: 50%;
                transition: all 0.2s ease;
            }

            .dolibarr-select-clear:hover {
                background: #e0e0e0;
                color: #333333;
            }

            .dolibarr-select-clear.visible {
                display: flex;
            }

            /* Dropdown */
            .dolibarr-select-dropdown {
                display: none;
                position: absolute;
                top: calc(100% + 4px);
                left: 0;
                right: 0;
                background: #ffffff;
                border: 1px solid #d0d0d0;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                overflow: hidden;
                animation: dolibarrDropdownFade 0.2s ease;
            }

            .dolibarr-select-dropdown.open {
                display: block;
            }

            @keyframes dolibarrDropdownFade {
                from {
                    opacity: 0;
                    transform: translateY(-8px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Champ de recherche */
            .dolibarr-select-search {
                padding: 8px;
                border-bottom: 1px solid #e0e0e0;
            }

            .dolibarr-select-search-input {
                width: 100%;
                padding: 6px 10px;
                border: 1px solid #d0d0d0;
                border-radius: 3px;
                font-size: 13px;
                outline: none;
                transition: border-color 0.2s ease;
                box-sizing: border-box;
            }

            .dolibarr-select-search-input:focus {
                border-color: #4a90d9;
                box-shadow: 0 0 0 2px rgba(74, 144, 217, 0.1);
            }

            /* Options */
            .dolibarr-select-options {
                overflow-y: auto;
                max-height: inherit;
                padding: 4px 0;
            }

            /* Groupe */
            .dolibarr-select-group {
                padding: 0;
            }

            .dolibarr-select-group-label {
                padding: 6px 12px;
                font-size: 11px;
                font-weight: 600;
                color: #666666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                background: #f8f9fa;
                border-bottom: 1px solid #e0e0e0;
            }

            /* Option */
            .dolibarr-select-option {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 12px;
                cursor: pointer;
                transition: all 0.15s ease;
                color: #333333;
            }

            .dolibarr-select-option:hover {
                background: #e8f0fe;
                color: #1a73e8;
            }

            .dolibarr-select-option.selected {
                background: #e8f0fe;
                color: #1a73e8;
                font-weight: 500;
            }

            .dolibarr-select-option.selected .dolibarr-select-check {
                display: inline-block;
            }

            .dolibarr-select-check {
                display: none;
                color: #1a73e8;
                font-size: 14px;
                margin-left: 8px;
            }

            .dolibarr-select-option.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .dolibarr-select-option.disabled:hover {
                background: transparent;
            }

            .dolibarr-select-empty {
                padding: 20px 12px;
                text-align: center;
                color: #999999;
                font-style: italic;
            }

            /* Scrollbar personnalisée */
            .dolibarr-select-options::-webkit-scrollbar {
                width: 6px;
            }

            .dolibarr-select-options::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 3px;
            }

            .dolibarr-select-options::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 3px;
            }

            .dolibarr-select-options::-webkit-scrollbar-thumb:hover {
                background: #a8a8a8;
            }

            /* États */
            .dolibarr-select-display.error {
                border-color: #d32f2f;
            }

            .dolibarr-select-display.error:focus {
                box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.15);
            }

            .dolibarr-select-display.success {
                border-color: #2e7d32;
            }

            .dolibarr-select-display.success:focus {
                box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
            }

            /* Variantes de taille */
            .dolibarr-select-display.small {
                min-height: 30px;
                padding: 2px 28px 2px 6px;
                font-size: 12px;
            }

            .dolibarr-select-display.large {
                min-height: 46px;
                padding: 6px 36px 6px 12px;
                font-size: 16px;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .dolibarr-select-display {
                    min-height: 44px;
                    padding: 6px 32px 6px 10px;
                    font-size: 16px;
                }
                
                .dolibarr-select-dropdown {
                    position: fixed;
                    top: auto;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    max-height: 50vh !important;
                    border-radius: 12px 12px 0 0;
                    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.2);
                }
            }
        </style>';

        $js = '
        <script>
            class DolibarrSelect {
                constructor(element) {
                    this.wrapper = element;
                    this.display = this.wrapper.querySelector(".dolibarr-select-display");
                    this.dropdown = this.wrapper.querySelector(".dolibarr-select-dropdown");
                    this.hiddenInput = this.wrapper.querySelector("input[type=\"hidden\"]");
                    this.options = this.wrapper.querySelectorAll(".dolibarr-select-option");
                    this.textSpan = this.display.querySelector(".dolibarr-select-text");
                    this.clearBtn = this.display.querySelector(".dolibarr-select-clear");
                    this.searchInput = this.wrapper.querySelector(".dolibarr-select-search-input");
                    this.isMultiple = this.hiddenInput.hasAttribute("multiple");
                    this.maxSelected = this.hiddenInput.dataset.maxSelected ? parseInt(this.hiddenInput.dataset.maxSelected) : null;
                    
                    this.isOpen = false;
                    this.selectedValues = this.hiddenInput.value ? this.hiddenInput.value.split(",") : [];
                    
                    this.init();
                    this.updateDisplay();
                }
                
                init() {
                    this.display.addEventListener("click", (e) => {
                        if (this.display.classList.contains("disabled")) return;
                        this.toggleDropdown();
                    });
                    
                    this.options.forEach(option => {
                        option.addEventListener("click", (e) => {
                            if (option.classList.contains("disabled")) return;
                            const value = option.dataset.value;
                            if (this.isMultiple) {
                                this.toggleOption(value);
                            } else {
                                this.selectOption(value);
                            }
                        });
                    });
                    
                    if (this.clearBtn) {
                        this.clearBtn.addEventListener("click", (e) => {
                            e.stopPropagation();
                            this.clearSelection();
                        });
                    }
                    
                    if (this.searchInput) {
                        this.searchInput.addEventListener("input", (e) => {
                            this.filterOptions(e.target.value);
                        });
                        
                        this.searchInput.addEventListener("click", (e) => {
                            e.stopPropagation();
                        });
                    }
                    
                    document.addEventListener("click", (e) => {
                        if (!this.wrapper.contains(e.target)) {
                            this.closeDropdown();
                        }
                    });
                    
                    document.addEventListener("keydown", (e) => {
                        if (e.key === "Escape" && this.isOpen) {
                            this.closeDropdown();
                        }
                    });
                    
                    this.display.addEventListener("keydown", (e) => {
                        if (e.key === "Enter" || e.key === " ") {
                            e.preventDefault();
                            this.toggleDropdown();
                        }
                    });
                }
                
                toggleDropdown() {
                    this.isOpen ? this.closeDropdown() : this.openDropdown();
                }
                
                openDropdown() {
                    this.isOpen = true;
                    this.dropdown.classList.add("open");
                    this.display.classList.add("active");
                    
                    if (this.searchInput) {
                        setTimeout(() => this.searchInput.focus(), 100);
                    }
                    
                    const selected = this.dropdown.querySelector(".dolibarr-select-option.selected");
                    if (selected) {
                        selected.scrollIntoView({ block: "nearest" });
                    }
                }
                
                closeDropdown() {
                    this.isOpen = false;
                    this.dropdown.classList.remove("open");
                    this.display.classList.remove("active");
                    if (this.searchInput) {
                        this.searchInput.value = "";
                        this.filterOptions("");
                    }
                }
                
                toggleOption(value) {
                    const index = this.selectedValues.indexOf(value);
                    if (index > -1) {
                        this.selectedValues.splice(index, 1);
                    } else {
                        if (this.maxSelected && this.selectedValues.length >= this.maxSelected) {
                            alert("Maximum " + this.maxSelected + " sélection(s) autorisée(s)");
                            return;
                        }
                        this.selectedValues.push(value);
                    }
                    
                    this.updateHiddenInput();
                    this.updateOptionsUI();
                    this.updateDisplay();
                    this.triggerChange();
                }
                
                selectOption(value) {
                    this.selectedValues = [value];
                    this.updateHiddenInput();
                    this.updateOptionsUI();
                    this.updateDisplay();
                    this.triggerChange();
                    this.closeDropdown();
                }
                
                updateHiddenInput() {
                    this.hiddenInput.value = this.selectedValues.join(",");
                }
                
                updateOptionsUI() {
                    this.options.forEach(option => {
                        const value = option.dataset.value;
                        const isSelected = this.selectedValues.includes(value);
                        option.classList.toggle("selected", isSelected);
                        
                        let check = option.querySelector(".dolibarr-select-check");
                        if (isSelected && !check) {
                            check = document.createElement("span");
                            check.className = "dolibarr-select-check";
                            check.textContent = "✓";
                            option.appendChild(check);
                        } else if (!isSelected && check) {
                            check.remove();
                        }
                    });
                }
                
                updateDisplay() {
                    // Mettre à jour les tags
                    const existingTags = this.display.querySelectorAll(".dolibarr-select-tag");
                    existingTags.forEach(tag => tag.remove());
                    
                    if (this.selectedValues.length === 0) {
                        this.textSpan.textContent = this.textSpan.dataset.placeholder || "Sélectionner...";
                        this.textSpan.classList.add("placeholder");
                        this.textSpan.style.display = "block";
                    } else if (this.isMultiple) {
                        this.textSpan.style.display = "none";
                        this.selectedValues.forEach(value => {
                            const option = this.wrapper.querySelector(`.dolibarr-select-option[data-value="${value}"]`);
                            if (option) {
                                const label = option.querySelector("span").textContent;
                                const tag = document.createElement("span");
                                tag.className = "dolibarr-select-tag";
                                tag.innerHTML = `
                                    <span class="dolibarr-select-tag-text">${label}</span>
                                    <span class="dolibarr-select-tag-remove" data-value="${value}">×</span>
                                `;
                                tag.querySelector(".dolibarr-select-tag-remove").addEventListener("click", (e) => {
                                    e.stopPropagation();
                                    this.toggleOption(value);
                                });
                                this.display.insertBefore(tag, this.textSpan);
                            }
                        });
                    } else {
                        this.textSpan.classList.remove("placeholder");
                        this.textSpan.style.display = "block";
                        const selectedOption = this.wrapper.querySelector(`.dolibarr-select-option.selected`);
                        if (selectedOption) {
                            this.textSpan.textContent = selectedOption.querySelector("span").textContent;
                        }
                    }
                    
                    this.updateClearButton();
                }
                
                clearSelection() {
                    this.selectedValues = [];
                    this.updateHiddenInput();
                    this.updateOptionsUI();
                    this.updateDisplay();
                    this.triggerChange();
                }
                
                updateClearButton() {
                    if (this.clearBtn) {
                        if (this.selectedValues.length > 0) {
                            this.clearBtn.classList.add("visible");
                        } else {
                            this.clearBtn.classList.remove("visible");
                        }
                    }
                }
                
                triggerChange() {
                    const event = new Event("change", { bubbles: true });
                    this.hiddenInput.dispatchEvent(event);
                }
                
                filterOptions(searchText) {
                    const searchLower = searchText.toLowerCase().trim();
                    this.options.forEach(option => {
                        const text = option.querySelector("span").textContent.toLowerCase();
                        if (text.includes(searchLower) || searchLower === "") {
                            option.style.display = "flex";
                        } else {
                            option.style.display = "none";
                        }
                    });
                    
                    this.wrapper.querySelectorAll(".dolibarr-select-group").forEach(group => {
                        const visibleOptions = group.querySelectorAll(".dolibarr-select-option[style*=\"display: flex\"]");
                        const label = group.querySelector(".dolibarr-select-group-label");
                        if (label) {
                            label.style.display = visibleOptions.length > 0 ? "block" : "none";
                        }
                    });
                }
                
                setValue(value) {
                    if (Array.isArray(value)) {
                        this.selectedValues = value;
                    } else {
                        this.selectedValues = [value];
                    }
                    this.updateHiddenInput();
                    this.updateOptionsUI();
                    this.updateDisplay();
                    this.triggerChange();
                }
                
                getValue() {
                    return this.isMultiple ? this.selectedValues : (this.selectedValues[0] || null);
                }
                
                getSelectedLabels() {
                    const labels = [];
                    this.selectedValues.forEach(value => {
                        const option = this.wrapper.querySelector(`.dolibarr-select-option[data-value="${value}"]`);
                        if (option) {
                            labels.push(option.querySelector("span").textContent);
                        }
                    });
                    return labels;
                }
                
                reset() {
                    this.clearSelection();
                    this.filterOptions("");
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".dolibarr-select-wrapper").forEach(wrapper => {
                    new DolibarrSelect(wrapper);
                });
            });
        </script>';

        return $css . $js;
    }

    private function getSelectedLabels() {
        $labels = [];
        foreach ($this->options as $option) {
            if (in_array($option['value'], $this->selected)) {
                $labels[] = $option['label'];
            }
        }
        return $labels;
    }

    public function render() {
        $assets = $this->renderAssets();
        
        $uniqid = uniqid('dolibarr_');
        $selectedLabels = $this->getSelectedLabels();
        $disabledClass = $this->disabled ? 'disabled' : '';
        $multipleAttr = $this->multiple ? 'multiple' : '';
        $nameAttr = $this->multiple ? $this->name . '[]' : $this->name;
        $valueAttr = $this->multiple ? implode(',', $this->selected) : ($this->selected[0] ?? '');

        $html = $assets;
        $html .= '<div class="dolibarr-select-wrapper" style="width: ' . htmlspecialchars($this->width) . ';">';
        
        // Champ caché
        $html .= '<input type="hidden" name="' . htmlspecialchars($nameAttr) . '" id="' . htmlspecialchars($this->id) . '" ';
        $html .= 'value="' . htmlspecialchars($valueAttr) . '" ';
        $html .= $multipleAttr . ' ';
        $html .= 'data-max-selected="' . ($this->maxSelected ?? '') . '" ';
        $html .= ($this->required ? 'required' : '') . '>';
        
        // Zone d'affichage
        $html .= '<div class="dolibarr-select-display ' . $disabledClass . ' ' . htmlspecialchars($this->class) . '" ';
        $html .= 'data-id="' . htmlspecialchars($this->id) . '" ';
        $html .= 'data-uniqid="' . $uniqid . '" ';
        $html .= 'tabindex="0">';
        
        // Icone
        if ($this->icon) {
            $html .= '<span class="dolibarr-select-icon">' . htmlspecialchars($this->icon) . '</span>';
        }
        
        // Texte sélectionné
        $placeholderClass = empty($this->selected) ? 'placeholder' : '';
        $html .= '<span class="dolibarr-select-text ' . $placeholderClass . '" data-placeholder="' . htmlspecialchars($this->placeholder) . '">';
        if (empty($this->selected)) {
            $html .= htmlspecialchars($this->placeholder);
        } elseif (!$this->multiple && !empty($this->selected)) {
            $html .= htmlspecialchars($selectedLabels[0] ?? '');
        } else {
            $html .= ''; // Les tags seront affichés par JS
        }
        $html .= '</span>';
        
        // Flèche
        $html .= '<span class="dolibarr-select-arrow">▼</span>';
        
        // Bouton clear
        if ($this->allowClear) {
            $clearVisible = !empty($this->selected) ? 'visible' : '';
            $html .= '<span class="dolibarr-select-clear ' . $clearVisible . '">✕</span>';
        }
        
        $html .= '</div>';
        
        // Dropdown
        $html .= '<div class="dolibarr-select-dropdown" id="dropdown_' . $uniqid . '" style="max-height: ' . htmlspecialchars($this->maxHeight) . ';">';
        
        if ($this->searchable) {
            $html .= '<div class="dolibarr-select-search">';
            $html .= '<input type="text" placeholder="Rechercher..." class="dolibarr-select-search-input">';
            $html .= '</div>';
        }
        
        $html .= '<div class="dolibarr-select-options">';
        
        if (empty($this->options)) {
            $html .= '<div class="dolibarr-select-empty">Aucune option disponible</div>';
        } else {
            $currentGroup = null;
            foreach ($this->options as $option) {
                if ($option['group'] && $option['group'] !== $currentGroup) {
                    if ($currentGroup !== null) {
                        $html .= '</div>';
                    }
                    $currentGroup = $option['group'];
                    $html .= '<div class="dolibarr-select-group">';
                    $html .= '<div class="dolibarr-select-group-label">' . htmlspecialchars($currentGroup) . '</div>';
                } elseif (!$option['group'] && $currentGroup !== null) {
                    if ($currentGroup !== null) {
                        $html .= '</div>';
                        $currentGroup = null;
                    }
                }
                
                $selected = in_array($option['value'], $this->selected) ? 'selected' : '';
                $html .= '<div class="dolibarr-select-option ' . $selected . '" data-value="' . htmlspecialchars($option['value']) . '">';
                $html .= '<span>' . htmlspecialchars($option['label']) . '</span>';
                if ($selected) {
                    $html .= '<span class="dolibarr-select-check">✓</span>';
                }
                $html .= '</div>';
            }
            if ($currentGroup !== null) {
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function __toString() {
        return $this->render();
    }
}

?>
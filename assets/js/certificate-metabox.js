/**
 * Certificate Metabox JavaScript
 * 
 * Handles certificate template selection in the Gutenberg editor
 * using vanilla JavaScript and the WordPress REST API.
 * 
 * @package TutorPress
 * @since 1.0.0
 */

(function() {
    'use strict';

    // Add debug log to check if script is loaded
    console.log('Certificate Metabox JS loaded');

    /**
     * Certificate Metabox Controller
     */
    const CertificateMetabox = {
        /**
         * DOM elements
         */
        elements: {
            container: null,
            tabs: null,
            tabContents: null,
            templateGrid: null,
            customCertGrid: null,
            loadingElements: null
        },

        /**
         * Data properties
         */
        data: {
            courseId: 0,
            currentTemplate: '',
            templates: [],
            customTemplates: [],
            isSaving: false
        },

        /**
         * Initialize the certificate metabox
         */
        init: function() {
            // Get DOM elements
            this.elements.container = document.getElementById('tutorpress-certificate-builder');
            
            // Debug log to check if container exists
            console.log('Certificate container found:', !!this.elements.container);
            
            if (!this.elements.container) {
                console.error('Certificate metabox container not found in DOM');
                return;
            }

            this.data.courseId = parseInt(this.elements.container.dataset.courseId, 10);
            this.data.currentTemplate = this.elements.container.dataset.currentTemplate || 'default';
            
            // Debug log for data
            console.log('Certificate metabox initialized with:', {
                courseId: this.data.courseId,
                currentTemplate: this.data.currentTemplate
            });
            
            this.elements.tabs = this.elements.container.querySelectorAll('.tutorpress-certificate-tab');
            this.elements.tabContents = this.elements.container.querySelectorAll('.tutorpress-certificate-tab-content');
            this.elements.templateGrid = this.elements.container.querySelector('[data-tab-content="templates"] .tutorpress-certificate-grid');
            this.elements.customCertGrid = this.elements.container.querySelector('[data-tab-content="custom-certificates"] .tutorpress-certificate-grid');
            this.elements.loadingElements = this.elements.container.querySelectorAll('.tutorpress-certificate-loading');

            // Set up event listeners
            this.setupEventListeners();
            
            // Load templates
            this.fetchTemplates();
        },

        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            // Tab switching
            this.elements.tabs.forEach(tab => {
                tab.addEventListener('click', this.handleTabClick.bind(this));
            });
        },

        /**
         * Handle tab click
         * 
         * @param {Event} event Click event
         */
        handleTabClick: function(event) {
            const tab = event.currentTarget;
            const tabName = tab.dataset.tab;
            
            // Update active tab
            this.elements.tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            // Update active content
            this.elements.tabContents.forEach(content => {
                if (content.dataset.tabContent === tabName) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });
        },

        /**
         * Fetch certificate templates from the REST API
         */
        fetchTemplates: function() {
            // Show loading indicators
            this.toggleLoading(true);
            
            console.log('Fetching certificate templates from:', '/tutorpress/v1/certificate-templates');
            
            // Fetch templates using the WordPress REST API
            wp.apiFetch({
                path: '/tutorpress/v1/certificate-templates',
                method: 'GET'
            }).then(response => {
                console.log('Certificate templates response:', response);
                
                if (!response.success && !Array.isArray(response)) {
                    this.showError(response.message || 'Error loading templates');
                    return;
                }
                
                // Process templates
                const templates = Array.isArray(response) ? response : (response.templates || []);
                
                // Separate templates into regular and custom
                this.data.templates = templates.filter(template => !template.is_custom && template.key !== 'none');
                this.data.customTemplates = templates.filter(template => template.is_custom);
                
                // Add "None" option to regular templates
                this.data.templates.unshift({
                    key: 'none',
                    name: 'None',
                    preview_src: '',
                    is_default: false
                });
                
                // Render templates
                this.renderTemplates();
                this.renderCustomTemplates();
                
                // Hide loading indicators
                this.toggleLoading(false);
            }).catch(error => {
                console.error('Error fetching certificate templates:', error);
                this.showError('Error loading templates. Please try again.');
                this.toggleLoading(false);
            });
        },

        /**
         * Render regular templates
         */
        renderTemplates: function() {
            if (!this.elements.templateGrid) {
                console.error('Template grid element not found');
                return;
            }
            
            // Clear existing content
            this.elements.templateGrid.innerHTML = '';
            
            // Create and append template cards
            this.data.templates.forEach(template => {
                const card = this.createTemplateCard(template);
                this.elements.templateGrid.appendChild(card);
            });
            
            // If no templates, show message
            if (this.data.templates.length === 0) {
                this.elements.templateGrid.innerHTML = '<div class="tutorpress-certificate-no-templates">No templates available.</div>';
            }
            
            console.log('Rendered', this.data.templates.length, 'regular templates');
        },

        /**
         * Render custom templates
         */
        renderCustomTemplates: function() {
            if (!this.elements.customCertGrid) {
                console.error('Custom certificate grid element not found');
                return;
            }
            
            // Clear existing content
            this.elements.customCertGrid.innerHTML = '';
            
            // Create and append template cards
            this.data.customTemplates.forEach(template => {
                const card = this.createTemplateCard(template);
                this.elements.customCertGrid.appendChild(card);
            });
            
            // If no custom templates, show message
            if (this.data.customTemplates.length === 0) {
                this.elements.customCertGrid.innerHTML = '<div class="tutorpress-certificate-no-templates">No custom certificates available.</div>';
            }
            
            console.log('Rendered', this.data.customTemplates.length, 'custom templates');
        },

        /**
         * Create a template card element
         * 
         * @param {Object} template Template data
         * @return {HTMLElement} Template card element
         */
        createTemplateCard: function(template) {
            const card = document.createElement('div');
            card.className = 'tutorpress-certificate-card';
            card.dataset.templateKey = template.key;
            
            // Add selected class if this is the current template
            if (template.key === this.data.currentTemplate) {
                card.classList.add('selected');
            }
            
            // Create card content
            let cardContent = '';
            
            // Image container (with or without image)
            cardContent += '<div class="tutorpress-certificate-image">';
            if (template.key === 'none') {
                cardContent += '<div class="tutorpress-certificate-none-placeholder">⊘</div>';
            } else if (template.preview_src) {
                cardContent += `<img src="${template.preview_src}" alt="${template.name}" loading="lazy">`;
            } else {
                cardContent += '<div class="tutorpress-certificate-no-preview">No preview</div>';
            }
            cardContent += '</div>';
            
            // Selected indicator
            cardContent += '<div class="tutorpress-certificate-selected"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path></svg></div>';
            
            // Name
            cardContent += `<div class="tutorpress-certificate-name">${template.name}</div>`;
            
            // Set card content
            card.innerHTML = cardContent;
            
            // Add click event listener
            card.addEventListener('click', () => {
                this.selectTemplate(template.key);
            });
            
            return card;
        },

        /**
         * Select a template
         * 
         * @param {string} templateKey The template key to select
         */
        selectTemplate: function(templateKey) {
            // Don't do anything if already saving
            if (this.data.isSaving) {
                return;
            }
            
            // Update UI to show selected template
            const cards = this.elements.container.querySelectorAll('.tutorpress-certificate-card');
            cards.forEach(card => {
                if (card.dataset.templateKey === templateKey) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
            
            // Save template selection if different from current
            if (templateKey !== this.data.currentTemplate) {
                this.saveTemplateSelection(templateKey);
            }
        },

        /**
         * Save template selection to the course meta
         * 
         * @param {string} templateKey The template key to save
         */
        saveTemplateSelection: function(templateKey) {
            // Set saving state
            this.data.isSaving = true;
            
            // Create small overlay to indicate saving
            const savingIndicator = document.createElement('div');
            savingIndicator.className = 'tutorpress-certificate-saving';
            savingIndicator.textContent = 'Saving...';
            this.elements.container.appendChild(savingIndicator);
            
            console.log('Saving certificate template:', templateKey);
            
            // Save template using WordPress REST API
            wp.apiFetch({
                path: '/tutorpress/v1/certificate-template',
                method: 'POST',
                data: {
                    course_id: this.data.courseId,
                    template_key: templateKey
                }
            }).then(response => {
                console.log('Save template response:', response);
                
                if (response.success) {
                    // Update current template
                    this.data.currentTemplate = templateKey;
                    
                    // Update saving indicator to show success
                    savingIndicator.textContent = 'Saved';
                    savingIndicator.classList.add('success');
                    
                    // Remove indicator after a delay
                    setTimeout(() => {
                        savingIndicator.remove();
                        this.data.isSaving = false;
                    }, 1500);
                } else {
                    throw new Error(response.message || 'Error saving template');
                }
            }).catch(error => {
                console.error('Error saving certificate template:', error);
                
                // Update indicator to show error
                savingIndicator.textContent = 'Error saving';
                savingIndicator.classList.add('error');
                
                // Remove indicator after a delay
                setTimeout(() => {
                    savingIndicator.remove();
                    this.data.isSaving = false;
                    
                    // Reset UI to match current template
                    this.selectTemplate(this.data.currentTemplate);
                }, 2000);
            });
        },

        /**
         * Toggle loading state
         * 
         * @param {boolean} show Whether to show or hide loading
         */
        toggleLoading: function(show) {
            this.elements.loadingElements.forEach(el => {
                el.style.display = show ? 'block' : 'none';
            });
        },

        /**
         * Show error message
         * 
         * @param {string} message Error message
         */
        showError: function(message) {
            // Replace loading elements with error message
            this.elements.loadingElements.forEach(el => {
                el.innerHTML = `<div class="tutorpress-certificate-error">${message}</div>`;
                el.style.display = 'block';
            });
        }
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM ready, initializing Certificate Metabox');
        CertificateMetabox.init();
    });

})(); 
// Curriculum Builder Metabox Script
document.addEventListener("DOMContentLoaded", function () {
  // Constants
  const SELECTORS = {
    container: ".tutorpress-curriculum-builder",
    topicsContainer: ".tutorpress-topics-container",
    addTopicBtn: ".tutorpress-add-topic",
    topic: ".tutorpress-topic",
    topicHeader: ".tutorpress-topic-header",
    topicContent: ".tutorpress-topic-content",
    topicTitle: ".tutorpress-topic-title",
    dragHandle: ".tutorpress-drag-handle",
    topicForm: ".tutorpress-topic-new",
    topicTitleInput: ".tutorpress-topic-title-input",
    topicSummaryInput: ".tutorpress-topic-summary-input",
    saveTopicBtn: ".tutorpress-btn-save",
    cancelTopicBtn: ".tutorpress-btn-cancel",
    deleteTopicBtn: ".tutorpress-topic-delete",
    editTopicBtn: ".tutorpress-topic-edit",
  };

  const TEMPLATES = {
    topicForm: (data = {}) => `
        <div class="tutorpress-topic-header" ${data.order ? `data-order="${data.order}"` : ""}>
          <div class="tutorpress-topic-header-left">
            <span class="tutorpress-drag-handle tutor-icon-drag disabled"></span>
            <input type="text" 
                   class="tutorpress-topic-title-input" 
                   value="${data.title || ""}"
                   placeholder="${window?.tutorpressData?.i18n?.addTopic || "Add Topic Title"}"
                   required>
          </div>
        </div>
        <div class="tutorpress-topic-content">
          <div class="tutorpress-form-group">
            <textarea class="tutorpress-topic-summary-input" 
                      placeholder="Add a summary">${data.summary || ""}</textarea>
          </div>
          <div class="tutorpress-form-actions">
            <button type="button" class="tutorpress-btn tutorpress-btn-cancel">
              ${window?.tutorpressData?.i18n?.cancel || "Cancel"}
            </button>
            <button type="button" class="tutorpress-btn tutorpress-btn-save" ${!data.title ? "disabled" : ""}>
              ${window?.tutorpressData?.i18n?.save || "Ok"}
            </button>
          </div>
          <hr class="tutorpress-divider" />
          ${data.contentItems || ""}
          ${
            data.contentActions ||
            `
            <div class="tutorpress-content-actions disabled">
              <button type="button" class="tutorpress-add-lesson" disabled>
                <span class="tutor-icon-plus-square"></span>
                ${window?.tutorpressData?.i18n?.addLesson || "Lesson"}
              </button>
              <button type="button" class="tutorpress-add-quiz" disabled>
                <span class="tutor-icon-plus-square"></span>
                ${window?.tutorpressData?.i18n?.addQuiz || "Quiz"}
              </button>
              <button type="button" class="tutorpress-add-interactive-quiz" disabled>
                <span class="tutor-icon-plus-square"></span>
                Interactive Quiz
              </button>
              <button type="button" class="tutorpress-add-assignment" disabled>
                <span class="tutor-icon-plus-square"></span>
                ${window?.tutorpressData?.i18n?.addAssignment || "Assignment"}
              </button>
            </div>
          `
          }
        </div>
      `,
  };

  const TOPIC_TEMPLATE = `
      <div class="tutorpress-topic tutorpress-topic-new">
        <div class="tutorpress-topic-header">
          <span class="tutorpress-drag-handle tutor-icon-drag disabled">
            <span class="tutor-icon-drag"></span>
          </span>
          <input type="text" class="tutorpress-topic-title-input" placeholder="Add a topic title" />
        </div>
        <div class="tutorpress-topic-content">
          <textarea class="tutorpress-topic-summary-input" placeholder="Add a summary (optional)"></textarea>
          <div class="tutorpress-form-actions">
            <button type="button" class="tutorpress-btn tutorpress-btn-cancel">Cancel</button>
            <button type="button" class="tutorpress-btn tutorpress-btn-save" disabled>Ok</button>
          </div>
          <hr class="tutorpress-divider" />
          <div class="tutorpress-content-actions disabled">
            <button type="button" disabled>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z" fill="currentColor"/>
              </svg>
              Lesson
            </button>
            <button type="button" disabled>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z" fill="currentColor"/>
              </svg>
              Quiz
            </button>
            <button type="button" disabled>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z" fill="currentColor"/>
              </svg>
              Assignment
            </button>
          </div>
        </div>
      </div>
    `;

  class CurriculumBuilder {
    constructor() {
      this.container = document.querySelector(SELECTORS.container);
      if (!this.container) return;

      this.topicsContainer = this.container.querySelector(SELECTORS.topicsContainer);
      this.addTopicBtn = this.container.querySelector(SELECTORS.addTopicBtn);
      this.courseId = parseInt(this.container.dataset.courseId, 10);

      this.bindEvents();
    }

    bindEvents() {
      // Add topic button click
      this.addTopicBtn.addEventListener("click", () => this.handleAddTopic());

      // Event delegation for dynamic elements
      this.container.addEventListener("click", (e) => {
        const target = e.target;

        if (target.matches(SELECTORS.cancelTopicBtn)) {
          e.preventDefault();
          this.handleCancelTopic(target);
        }

        if (target.matches(SELECTORS.saveTopicBtn)) {
          e.preventDefault();
          this.handleSaveTopic(target);
        }

        if (target.matches(SELECTORS.deleteTopicBtn)) {
          e.preventDefault();
          this.handleDeleteTopic(target);
        }

        if (target.matches(SELECTORS.editTopicBtn)) {
          e.preventDefault();
          const topic = target.closest(SELECTORS.topic);
          this.handleEditTopic(topic);
        }
      });

      // Handle double-click on topic title
      this.container.addEventListener("dblclick", (e) => {
        const target = e.target;
        if (target.matches(".tutorpress-topic-title")) {
          const topic = target.closest(SELECTORS.topic);
          if (topic && !topic.classList.contains("tutorpress-topic-new")) {
            this.handleEditTopic(topic);
          }
        }
      });
    }

    handleAddTopic() {
      const topicForm = document.createElement("div");
      topicForm.className = "tutorpress-topic tutorpress-topic-new";
      topicForm.innerHTML = TEMPLATES.topicForm();

      // Add form to container
      this.topicsContainer.appendChild(topicForm);

      // Focus title input
      const titleInput = topicForm.querySelector(SELECTORS.topicTitleInput);
      titleInput.focus();

      // Enable/disable save button based on title
      titleInput.addEventListener("input", () => {
        const saveBtn = topicForm.querySelector(SELECTORS.saveTopicBtn);
        saveBtn.disabled = !titleInput.value.trim();
      });
    }

    handleCancelTopic(cancelBtn) {
      const topic = cancelBtn.closest(SELECTORS.topic);

      if (topic.classList.contains("tutorpress-topic-new")) {
        topic.remove();
        return;
      }

      if (topic.classList.contains("editing")) {
        topic.innerHTML = topic.dataset.originalContent;
        topic.classList.remove("editing");
        delete topic.dataset.originalContent;
      }
    }

    async handleSaveTopic(saveBtn) {
      const topic = saveBtn.closest(SELECTORS.topic);
      if (!topic) return;

      const titleInput = topic.querySelector(SELECTORS.topicTitleInput);
      const summaryInput = topic.querySelector(SELECTORS.topicSummaryInput);
      const title = titleInput.value.trim();
      const summary = summaryInput.value.trim();
      const topicId = topic.classList.contains("editing") ? topic.dataset.topicId : null;
      const order = topic.dataset.originalOrder || topic.dataset.order || "0";

      // Store existing content items before saving
      const contentItems = topic.querySelector(".tutorpress-content-items")?.outerHTML || "";
      const contentActions = topic.querySelector(".tutorpress-content-actions")?.outerHTML || "";

      if (!title) return;

      try {
        // Show loading state
        saveBtn.disabled = true;
        saveBtn.textContent = window?.tutorpressData?.i18n?.saving || "Saving...";

        const response = await fetch(`${window?.tutorpressData?.restUrl || "/wp-json/tutorpress/v1"}/topic`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": window?.tutorpressData?.restNonce,
          },
          body: JSON.stringify({
            course_id: this.courseId,
            topic_id: topicId,
            title,
            summary,
            order: parseInt(order, 10),
          }),
        });

        if (!response.ok) {
          throw new Error("Failed to save topic");
        }

        const data = await response.json();

        // Update topic content while preserving content items
        if (topic.classList.contains("editing")) {
          topic.classList.remove("editing");
          delete topic.dataset.originalContent;
          delete topic.dataset.originalOrder;
        }

        // Get the base topic HTML
        const baseHtml = this.getTopicHtml(data);

        // Create a temporary container to parse the HTML
        const temp = document.createElement("div");
        temp.innerHTML = baseHtml;

        // Replace the content items and actions with the preserved ones
        if (contentItems || contentActions) {
          const contentContainer = temp.querySelector(".tutorpress-topic-content");
          if (contentContainer) {
            // Remove the default empty content items and actions
            contentContainer.querySelector(".tutorpress-content-items")?.remove();
            contentContainer.querySelector(".tutorpress-content-actions")?.remove();

            // Append the preserved content
            contentContainer.innerHTML += contentItems + contentActions;
          }
        }

        // Update the topic with the complete HTML
        topic.innerHTML = temp.innerHTML;
        topic.className = "tutorpress-topic";
        topic.setAttribute("data-topic-id", data.id);
        topic.setAttribute("data-order", data.order || order);
      } catch (error) {
        // Show error state
        saveBtn.textContent = window?.tutorpressData?.i18n?.error || "Error";
        saveBtn.classList.add("tutorpress-error");

        console.error("Error saving topic:", error);

        // Reset button after delay
        setTimeout(() => {
          saveBtn.disabled = false;
          saveBtn.textContent = window?.tutorpressData?.i18n?.save || "Save";
          saveBtn.classList.remove("tutorpress-error");
        }, 2000);
      }
    }

    async handleDeleteTopic(deleteBtn) {
      const topicItem = deleteBtn.closest(".tutorpress-topic");
      if (!topicItem) return;

      const topicId = parseInt(topicItem.getAttribute("data-topic-id"), 10);
      if (!topicId) return;

      if (!confirm(window?.tutorpressData?.i18n?.confirmDelete || "Are you sure you want to delete this?")) return;

      try {
        const response = await fetch(
          `${window?.tutorpressData?.restUrl || "/wp-json/tutorpress/v1"}/curriculum/topic`,
          {
            method: "DELETE",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": window?.tutorpressData?.restNonce,
            },
            body: JSON.stringify({ topic_id: topicId }),
          }
        );

        if (!response.ok) {
          throw new Error("Failed to delete topic");
        }

        // Remove topic from DOM
        topicItem.remove();
      } catch (error) {
        console.error("Error deleting topic:", error);
        alert(window?.tutorpressData?.i18n?.error || "Error");
      }
    }

    handleEditTopic(topic) {
      if (topic.classList.contains("editing")) return;

      const topicId = topic.dataset.topicId;
      const titleEl = topic.querySelector(".tutorpress-topic-title");
      const summaryEl = topic.querySelector(".tutorpress-topic-summary");
      const currentTitle = titleEl.textContent.trim();
      const currentSummary = summaryEl ? summaryEl.textContent.trim() : "";
      const currentOrder = topic.querySelector(".tutorpress-topic-header").dataset.order || "0";

      // Store original content and order for cancel action
      topic.dataset.originalContent = topic.innerHTML;
      topic.dataset.originalOrder = currentOrder;

      // Get the existing content items and actions
      const contentItems = topic.querySelector(".tutorpress-content-items")?.outerHTML || "";
      const contentActions = topic.querySelector(".tutorpress-content-actions")?.outerHTML || "";

      // Create edit form using the shared template
      topic.innerHTML = TEMPLATES.topicForm({
        title: currentTitle,
        summary: currentSummary,
        order: currentOrder,
        contentItems,
        contentActions,
      });
      topic.classList.add("editing");

      // Focus title input
      const titleInput = topic.querySelector(".tutorpress-topic-title-input");
      titleInput.focus();
      titleInput.setSelectionRange(titleInput.value.length, titleInput.value.length);
    }

    escapeHtml(unsafe) {
      return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    getTopicHtml(topic) {
      return `
          <div class="tutorpress-topic-header" data-order="${topic.order || "0"}">
            <div class="tutorpress-topic-header-left">
              <span class="tutorpress-drag-handle tutor-icon-drag"></span>
              <button type="button" class="tutorpress-topic-toggle" aria-expanded="false">
                <span class="tutor-icon-angle-down"></span>
              </button>
              <h3 class="tutorpress-topic-title">${topic.title}</h3>
            </div>
            <div class="tutorpress-topic-header-right">
              <button type="button" class="tutorpress-topic-edit" title="${
                window?.tutorpressData?.i18n?.editTopic || "Edit Topic"
              }">
                <span class="tutor-icon-pencil"></span>
              </button>
              <button type="button" class="tutorpress-topic-duplicate" title="${
                window?.tutorpressData?.i18n?.duplicateTopic || "Duplicate Topic"
              }">
                <span class="tutor-icon-file-import"></span>
              </button>
              <button type="button" class="tutorpress-topic-delete" title="${
                window?.tutorpressData?.i18n?.deleteTopic || "Delete Topic"
              }">
                <span class="tutor-icon-trash-can"></span>
              </button>
            </div>
          </div>
          <div class="tutorpress-topic-content">
            ${topic.summary ? `<div class="tutorpress-topic-summary">${topic.summary}</div>` : ""}
            <div class="tutorpress-content-items"></div>
            <div class="tutorpress-content-actions">
              <button type="button" class="tutorpress-add-lesson">
                <span class="tutor-icon-plus-square"></span>
                ${window?.tutorpressData?.i18n?.addLesson || "Lesson"}
              </button>
              <button type="button" class="tutorpress-add-quiz">
                <span class="tutor-icon-plus-square"></span>
                ${window?.tutorpressData?.i18n?.addQuiz || "Quiz"}
              </button>
              <button type="button" class="tutorpress-add-interactive-quiz">
                <span class="tutor-icon-plus-square"></span>
                Interactive Quiz
              </button>
              <button type="button" class="tutorpress-add-assignment">
                <span class="tutor-icon-plus-square"></span>
                ${window?.tutorpressData?.i18n?.addAssignment || "Assignment"}
              </button>
            </div>
          </div>
        `;
    }
  }

  // Initialize when DOM is ready
  if (document.querySelector(SELECTORS.container)) {
    const builder = new CurriculumBuilder();

    // Add the "Add Topic" button at the bottom if it doesn't exist
    const container = document.querySelector(SELECTORS.container);
    const existingButton = container.querySelector(SELECTORS.addTopicBtn);

    if (!existingButton) {
      const addTopicButton = document.createElement("button");
      addTopicButton.className = "tutorpress-add-topic";
      addTopicButton.textContent = "Add Topic";
      container.appendChild(addTopicButton);
    }
  }
});

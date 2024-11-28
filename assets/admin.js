document.addEventListener("DOMContentLoaded", function () {
  const { createApp } = Vue;
  const { createRouter, createWebHashHistory } = VueRouter;

  const AdminPage = { template: `<h2>Welcome to Vue Admin SPA!</h2>` };

  const AddOptions = {
    data() {
      return { textOption: "", imageOption: null, saving: false, message: "" };
    },
    methods: {
      handleImageUpload(event) {
        this.imageOption = event.target.files[0];
      },
      async saveOptions() {
        const formData = new FormData();
        formData.append("action", "save_vue_options");
        formData.append("text_option", this.textOption);
        if (this.imageOption) {
          formData.append("image_option", this.imageOption);
        }
        formData.append("nonce", vueAdmin.nonce);

        this.saving = true;
        try {
          const response = await fetch(vueAdmin.ajaxUrl, {
            method: "POST",
            body: formData,
          });
          const result = await response.json();
          this.message = result.success
            ? result.data.message
            : "Error saving options.";
        } catch {
          this.message = "Error occurred.";
        }
        this.saving = false;
      },
    },
    template: `
      <div>
        <h2>Add Options</h2>
        <input v-model="textOption" placeholder="Enter text option" />
        <input type="file" @change="handleImageUpload" />
        <button @click="saveOptions" :disabled="saving">Save</button>
        <p>{{ message }}</p>
      </div>
    `,
  };

  const BlogTitles = {
    data() {
      return { blogTitles: [], error: "" };
    },
    async created() {
      try {
        const response = await fetch(
          `${vueAdmin.ajaxUrl}?action=fetch_blog_titles&nonce=${vueAdmin.nonce}`,
          { method: "POST" }
        );
        const result = await response.json();
        this.blogTitles = result.success ? result.data : [];
      } catch {
        this.error = "Error loading blog titles.";
      }
    },
    template: `
      <div>
        <h2>Blog Titles</h2>
        <ul>
          <li v-for="post in blogTitles" :key="post.permalink">
            <a :href="post.permalink" target="_blank">{{ post.title }}</a>
          </li>
        </ul>
      </div>
    `,
  };

  const DeletePath = {
    data() {
      return { filePath: "", message: "" };
    },
    methods: {
      async deleteFile() {
        if (!this.filePath) {
          this.message = "File path is required.";
          return;
        }

        const formData = new FormData();
        formData.append("action", "delete_file_path");
        formData.append("file_path", this.filePath);
        formData.append("nonce", vueAdmin.nonce);

        try {
          const response = await fetch(vueAdmin.ajaxUrl, {
            method: "POST",
            body: formData,
          });

          const result = await response.json();
          this.message = result.success
            ? result.data.message
            : result.data.message || "Error deleting file.";
        } catch (error) {
          console.error("Error deleting file:", error);
          this.message = "Error occurred.";
        }
      },
    },
    template: `
      <div>
        <h2>Delete File by Path</h2>
        <input v-model="filePath" placeholder="Enter file path" />
        <button @click="deleteFile">Delete</button>
        <p>{{ message }}</p>
      </div>
    `,
  };

  const UserMeta = {
    data() {
      return {
        userId: "",
        metaKeys: [
          "student_course_subscribed",
          "student_course_start",
          "instructor_course_reset",
          "student_course_retake",
          "student_course_submit",
          "student_course_reset",
          "instructor_course_reset",
          "instructor_course_retake",
          "student_course_evaluation",
          "instructor_course_evaluation",
          "student_course_badge",
          "student_course_certificate",
          "student_course_review",
          "student_course_unsubscribe",
          "student_start_quiz",
          "student_quiz_reset",
          "instructor_quiz_reset",
          "student_quiz_submit",
        ],
        metaValue: "", // Dynamically changes between "yes" and "no"
        message: "",
      };
    },
    methods: {
      async updateMeta() {
        if (!this.userId || !this.metaKeys.length) {
          this.message = "User ID and Meta Keys are required.";
          return;
        }
  
        const formData = new FormData();
        formData.append("action", "update_user_meta_value");
        formData.append("user_id", this.userId);
        formData.append("meta_value", this.metaValue);
        formData.append("meta_keys", JSON.stringify(this.metaKeys)); // Pass keys as a JSON string
        formData.append("nonce", vueAdmin.nonce);
  
        try {
          const response = await fetch(vueAdmin.ajaxUrl, {
            method: "POST",
            body: formData,
          });
          const result = await response.json();
          this.message = result.success
            ? result.data.message
            : result.data.message || "Error updating meta.";
        } catch (error) {
          console.error("Error updating user meta:", error);
          this.message = "Error occurred while updating meta.";
        }
      },
    },
    template: `
      <div>
        <h2>Update User Notification Settings</h2>
        <div>
          <label>User ID:</label>
          <input v-model="userId" placeholder="Enter User ID" />
        </div>
        <div>
          <label>Show Notification:</label>
          <div>
            <label>
              <input type="radio" v-model="metaValue" value="yes" />
              Yes
            </label>
            <label>
              <input type="radio" v-model="metaValue" value="no" />
              No
            </label>
          </div>
        </div>
        <button @click="updateMeta">Update All Notifications</button>
        <p>{{ message }}</p>
      </div>
    `,
  };
  

  const routes = [
    { path: "/", redirect: "/admin-page" },
    { path: "/admin-page", component: AdminPage },
    { path: "/add-options", component: AddOptions },
    { path: "/blog-titles", component: BlogTitles },
    { path: "/delete-path", component: DeletePath },
    { path: "/user-meta", component: UserMeta },
  ];

  const router = createRouter({ history: createWebHashHistory(), routes });
  createApp({}).use(router).mount("#vue-admin-app");
});

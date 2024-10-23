document.addEventListener("DOMContentLoaded", function () {
  const { createApp } = Vue;
  const { createRouter, createWebHashHistory } = VueRouter;

  // Define the components for the pages
  const AdminPage = {
    template: `<div>
            <h2>Vue Admin Page</h2>
            <p>Welcome to the Vue Admin Page!</p>
        </div>`,
  };

  const AddOptions = {
    data() {
      return {
        textOption: "",
        bannerOption: null,
        saving: false,
        message: "",
      };
    },
    methods: {
      handleFileUpload(event) {
        this.bannerOption = event.target.files[0];
      },
      async saveOptions() {
        const formData = new FormData();
        formData.append("action", "save_vue_options");
        formData.append("text_option", this.textOption);
        if (this.bannerOption) {
          formData.append("banner_option", this.bannerOption);
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
            ? "Options saved successfully!"
            : "Failed to save options.";
        } catch (error) {
          this.message = "Error occurred while saving.";
        }
        this.saving = false;
      },
    },
    template: `
            <div>
                <h2>Add Options Fields</h2>
                <form @submit.prevent="saveOptions">
                    <div>
                        <label for="text_option">Text Option:</label>
                        <input type="text" v-model="textOption" id="text_option" />
                    </div>
                    <div>
                        <label for="banner_option">Upload Banner:</label>
                        <input type="file" @change="handleFileUpload" id="banner_option" />
                    </div>
                    <button type="submit" :disabled="saving">Save Options</button>
                    <p>{{ message }}</p>
                </form>
            </div>
        `,
  };

  const BlogTitles = {
    data() {
      return {
        blogTitles: [],
        loading: true,
        error: "",
      };
    },
    async created() {
      // Fetch blog titles when the component is created
      try {
        const response = await fetch(
          `${vueAdmin.ajaxUrl}?action=fetch_blog_titles&nonce=${vueAdmin.nonce}`,
          {
            method: "POST",
          }
        );
        const result = await response.json();
        if (result.success) {
          this.blogTitles = result.data;
        } else {
          this.error = "Failed to load blog titles.";
        }
      } catch (error) {
        this.error = "An error occurred while fetching blog titles.";
      } finally {
        this.loading = false;
      }
    },
    template: `
            <div>
                <h2>Blog Titles</h2>
                <div v-if="loading">Loading...</div>
                <div v-if="error">{{ error }}</div>
                <ul v-if="!loading && !error">
                    <li v-for="post in blogTitles" :key="post.permalink">
                        <a :href="post.permalink" target="_blank">{{ post.title }}</a>
                    </li>
                </ul>
            </div>
        `,
  };

  // Define the routes for the application
  const routes = [
    { path: "/admin-page", component: AdminPage },
    { path: "/add-options", component: AddOptions },
    { path: "/blog-titles", component: BlogTitles },
    { path: "/", redirect: "/admin-page" }, // Default route redirect
  ];

  // Initialize the router with defined routes
  const router = createRouter({
    history: createWebHashHistory(),
    routes,
  });

  // Initialize the Vue app with the router
  const app = createApp({});
  app.use(router);
  app.mount("#vue-admin-app");
});

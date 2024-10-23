<div id="vue-admin-app">
    <nav>
        <ul>
            <li><router-link to="/admin-page" active-class="active">Vue Admin Page</router-link></li>
            <li><router-link to="/add-options" active-class="active">Add Options</router-link></li>
            <li><router-link to="/blog-titles" active-class="active">Blog Titles</router-link></li>
        </ul>
    </nav>
    <router-view></router-view>
</div>
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { httpClient } from './api/httpClient'
import { registerAuthInterceptor } from './api/interceptors/authInterceptor'

registerAuthInterceptor(httpClient)

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token') || null,
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        async login(email, password) {
            const response = await axios.post('/api/login', { email, password })
            this.token = response.data.access_token
            this.user = response.data.user
            localStorage.setItem('token', this.token)
            axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
        },
        async fetchUser() {
            if (!this.token) return
            try {
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
                const response = await axios.get('/api/me')
                this.user = response.data
            } catch (error) {
                this.logout()
            }
        },
        logout() {
            if (this.token) {
                axios.post('/api/logout').catch(() => { })
            }
            this.user = null
            this.token = null
            localStorage.removeItem('token')
            delete axios.defaults.headers.common['Authorization']
        }
    }
})

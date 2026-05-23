/* ============================================
   NuruXplore - API Client
   ============================================ */

class NuruAPI {
    constructor(baseURL = '/api') {
        this.baseURL = baseURL;
        this.token = localStorage.getItem('nuruxplore_token') || '';
    }

    setToken(token) {
        this.token = token;
        localStorage.setItem('nuruxplore_token', token);
    }

    clearToken() {
        this.token = '';
        localStorage.removeItem('nuruxplore_token');
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const headers = {
            'Accept': 'application/json',
            ...options.headers,
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers,
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error(`API Error [${endpoint}]:`, error);
            throw error;
        }
    }

    // Auth
    async register(name, email, password, passwordConfirmation) {
        return this.request('/auth/register', {
            method: 'POST',
            body: { name, email, password, password_confirmation: passwordConfirmation },
        });
    }

    async login(email, password) {
        return this.request('/auth/login', {
            method: 'POST',
            body: { email, password },
        });
    }

    async logout() {
        return this.request('/auth/logout', { method: 'POST' });
    }

    async getUser() {
        return this.request('/auth/user');
    }

    // Projects
    async getProjects() {
        return this.request('/projects');
    }

    async createProject(data) {
        return this.request('/projects', {
            method: 'POST',
            body: data,
        });
    }

    async getProject(id) {
        return this.request(`/projects/${id}`);
    }

    async updateProject(id, data) {
        return this.request(`/projects/${id}`, {
            method: 'PUT',
            body: data,
        });
    }

    async deleteProject(id) {
        return this.request(`/projects/${id}`, { method: 'DELETE' });
    }

    async duplicateProject(id) {
        return this.request(`/projects/${id}/duplicate`, { method: 'POST' });
    }

    async generateOutline(projectId, topic) {
        return this.request(`/projects/${projectId}/generate-outline`, {
            method: 'POST',
            body: { topic },
        });
    }

    // Sections
    async getSections(projectId) {
        return this.request(`/projects/${projectId}/sections`);
    }

    async getSection(id) {
        return this.request(`/sections/${id}`);
    }

    async updateSection(id, data) {
        return this.request(`/sections/${id}`, {
            method: 'PUT',
            body: data,
        });
    }

    async deleteSection(id) {
        return this.request(`/sections/${id}`, { method: 'DELETE' });
    }

    async generateSection(sectionId) {
        return this.request(`/sections/${sectionId}/ai-generate`, { method: 'POST' });
    }

    async reviseSection(sectionId, instruction) {
        return this.request(`/sections/${sectionId}/ai-revise`, {
            method: 'POST',
            body: { instruction },
        });
    }

    // Sources
    async getSources(projectId) {
        return this.request(`/projects/${projectId}/sources`);
    }

    async addSource(projectId, data) {
        return this.request(`/projects/${projectId}/sources`, {
            method: 'POST',
            body: data,
        });
    }

    async uploadSource(projectId, file) {
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('file', file);
        
        return this.request('/sources/upload', {
            method: 'POST',
            body: formData,
        });
    }

    async verifySource(sourceId) {
        return this.request(`/sources/${sourceId}/verify`, { method: 'POST' });
    }

    async deleteSource(id) {
        return this.request(`/sources/${id}`, { method: 'DELETE' });
    }

    // Messages
    async getMessages(projectId) {
        return this.request(`/projects/${projectId}/messages`);
    }

    async sendMessage(projectId, message, actionType = 'chat') {
        return this.request(`/projects/${projectId}/messages`, {
            method: 'POST',
            body: { message, action_type: actionType },
        });
    }

    // Export
    async exportPDF(projectId) {
        return this.request(`/projects/${projectId}/export/pdf`, { method: 'POST' });
    }

    async exportWord(projectId) {
        return this.request(`/projects/${projectId}/export/word`, { method: 'POST' });
    }

    // Credits
    async getCredits() {
        return this.request('/credits/balance');
    }
}

// Create global instance
window.NuruAPI = new NuruAPI();
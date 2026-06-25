/* ============================================
   NuruXplore - API Client
   Updated for Research Profile Workflow
   ============================================ */

class NuruAPI {
    constructor(baseURL = '/api') {
        this.baseURL = baseURL;
        this.token = localStorage.getItem('nuruxplore_token') || '';
    }

    setToken(token) {
        this.token = token || '';
        if (this.token) {
            localStorage.setItem('nuruxplore_token', this.token);
        }
    }

    clearToken() {
        this.token = '';
        localStorage.removeItem('nuruxplore_token');
    }

    getToken() {
        return this.token || localStorage.getItem('nuruxplore_token') || '';
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;

        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {}),
        };

        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const requestOptions = {
            ...options,
            headers,
        };

        if (
            requestOptions.body &&
            typeof requestOptions.body === 'object' &&
            !(requestOptions.body instanceof FormData)
        ) {
            headers['Content-Type'] = 'application/json';
            requestOptions.body = JSON.stringify(requestOptions.body);
        }

        try {
            const response = await fetch(url, requestOptions);

            const contentType = response.headers.get('content-type') || '';
            let data = null;

            if (response.status !== 204) {
                if (contentType.includes('application/json')) {
                    data = await response.json();
                } else {
                    const text = await response.text();
                    data = text ? { message: text } : {};
                }
            }

            if (!response.ok) {
                const message =
                    data?.message ||
                    data?.error ||
                    this.formatValidationErrors(data?.errors) ||
                    `HTTP ${response.status}`;

                const error = new Error(message);
                error.status = response.status;
                error.data = data;
                throw error;
            }

            return data ?? {};
        } catch (error) {
            console.error(`API Error [${endpoint}]:`, error);
            throw error;
        }
    }

    formatValidationErrors(errors) {
        if (!errors || typeof errors !== 'object') return null;

        return Object.values(errors)
            .flat()
            .filter(Boolean)
            .join('\n');
    }

    /* ============================================
       Auth
       ============================================ */

    async register(name, email, password, passwordConfirmation) {
        return this.request('/auth/register', {
            method: 'POST',
            body: {
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
            },
        });
    }

    async login(email, password) {
        return this.request('/auth/login', {
            method: 'POST',
            body: { email, password },
        });
    }

    async logout() {
        const result = await this.request('/auth/logout', { method: 'POST' });
        this.clearToken();
        return result;
    }

    async getUser() {
        return this.request('/auth/user');
    }

    /* ============================================
       Projects
       ============================================ */

    async getProjects() {
        return this.request('/projects');
    }

    async createProject(data) {
        return this.request('/projects', {
            method: 'POST',
            body: data,
        });
    }

    async getProject(projectUuid) {
        return this.request(`/projects/${projectUuid}`);
    }

    async updateProject(projectUuid, data) {
        return this.request(`/projects/${projectUuid}`, {
            method: 'PUT',
            body: data,
        });
    }

    async deleteProject(projectUuid) {
        return this.request(`/projects/${projectUuid}`, {
            method: 'DELETE',
        });
    }

    async duplicateProject(projectUuid) {
        return this.request(`/projects/${projectUuid}/duplicate`, {
            method: 'POST',
        });
    }

    /*
     * Legacy full-generation endpoint.
     * Keep this for old dashboard buttons or quick demo mode.
     */
    async generateComplete(projectUuid, topic = null, type = 'thesis') {
        return this.request(`/projects/${projectUuid}/generate-complete`, {
            method: 'POST',
            body: {
                topic,
                type,
            },
        });
    }

    async getGenerationStatus(projectUuid) {
        return this.request(`/projects/${projectUuid}/generation-status`);
    }

    /* ============================================
       Research Profile Workflow
       ============================================ */

    async buildResearchProfile(projectUuid) {
        return this.request(`/projects/${projectUuid}/build-research-profile`, {
            method: 'POST',
        });
    }

    async updateResearchProfile(projectUuid, researchProfile) {
        return this.request(`/projects/${projectUuid}/research-profile`, {
            method: 'PUT',
            body: {
                research_profile: researchProfile,
            },
        });
    }

    async approveResearchProfile(projectUuid, researchProfile = null) {
        const body = researchProfile
            ? { research_profile: researchProfile }
            : {};

        return this.request(`/projects/${projectUuid}/approve-research-profile`, {
            method: 'POST',
            body,
        });
    }

    async getResearchProfile(projectUuid) {
        const project = await this.getProject(projectUuid);
        return {
            research_profile: project?.project?.research_profile || null,
            research_profile_status: project?.project?.research_profile_status || 'missing',
            project: project?.project || null,
        };
    }

    /* ============================================
       Outline
       ============================================ */

    async generateOutline(projectUuid, topic = null) {
        return this.request(`/projects/${projectUuid}/generate-outline`, {
            method: 'POST',
            body: topic ? { topic } : {},
        });
    }

    /* ============================================
       Sections
       ============================================ */

    async getSections(projectUuid) {
        return this.request(`/projects/${projectUuid}/sections`);
    }

    async createSection(projectUuid, data) {
        return this.request(`/projects/${projectUuid}/sections`, {
            method: 'POST',
            body: data,
        });
    }

    async getSection(sectionId) {
        return this.request(`/sections/${sectionId}`);
    }

    async updateSection(sectionId, data) {
        return this.request(`/sections/${sectionId}`, {
            method: 'PUT',
            body: data,
        });
    }

    async deleteSection(sectionId) {
        return this.request(`/sections/${sectionId}`, {
            method: 'DELETE',
        });
    }

    async reorderSections(sections) {
        return this.request('/sections/reorder', {
            method: 'POST',
            body: { sections },
        });
    }

    async generateSection(sectionId, options = {}) {
        return this.request(`/sections/${sectionId}/ai-generate`, {
            method: 'POST',
            body: options,
        });
    }

    async reviseSection(sectionId, instruction, options = {}) {
        return this.request(`/sections/${sectionId}/ai-revise`, {
            method: 'POST',
            body: {
                instruction,
                ...options,
            },
        });
    }

    /* ============================================
       Document Assembly / Quality Check
       ============================================ */

    async assembleDocument(projectUuid) {
        return this.request(`/projects/${projectUuid}/assemble-document`, {
            method: 'POST',
        });
    }

    async consistencyCheck(projectUuid) {
        return this.request(`/projects/${projectUuid}/consistency-check`, {
            method: 'POST',
        });
    }

    /* ============================================
       Sources / Uploads
       ============================================ */

    async getSources(projectUuid) {
        return this.request(`/projects/${projectUuid}/sources`);
    }

    async addSource(projectUuid, data) {
        return this.request(`/projects/${projectUuid}/sources`, {
            method: 'POST',
            body: data,
        });
    }

    /*
     * Flexible upload helper.
     *
     * Supports both old backend style:
     *   project_id = numeric database ID
     *
     * And improved backend style:
     *   project_uuid = UUID
     *
     * If you pass a numeric project identifier, it sends project_id.
     * If you pass a UUID/string identifier, it sends project_uuid.
     */
    async uploadSource(projectIdentifier, file, options = {}) {
        const formData = new FormData();

        if (Number.isInteger(projectIdentifier) || /^\d+$/.test(String(projectIdentifier))) {
            formData.append('project_id', projectIdentifier);
        } else {
            formData.append('project_uuid', projectIdentifier);
        }

        formData.append('file', file);

        if (options.title) {
            formData.append('title', options.title);
        }

        if (options.document_role) {
            formData.append('document_role', options.document_role);
        }

        if (options.type) {
            formData.append('type', options.type);
        }

        return this.request('/sources/upload', {
            method: 'POST',
            body: formData,
        });
    }

    async uploadProposal(projectIdentifier, file, title = null) {
        return this.uploadSource(projectIdentifier, file, {
            title: title || file.name,
            document_role: 'proposal',
            type: 'proposal',
        });
    }

    async uploadDataset(projectIdentifier, file, title = null) {
        return this.uploadSource(projectIdentifier, file, {
            title: title || file.name,
            document_role: 'dataset',
            type: 'dataset',
        });
    }

    async uploadReference(projectIdentifier, file, title = null) {
        return this.uploadSource(projectIdentifier, file, {
            title: title || file.name,
            document_role: 'reference',
            type: 'reference',
        });
    }

    async verifySource(sourceId) {
        return this.request(`/sources/${sourceId}/verify`, {
            method: 'POST',
        });
    }

    async deleteSource(sourceId) {
        return this.request(`/sources/${sourceId}`, {
            method: 'DELETE',
        });
    }

    /* ============================================
       Messages
       ============================================ */

    async getMessages(projectUuid) {
        return this.request(`/projects/${projectUuid}/messages`);
    }

    async sendMessage(projectUuid, message, actionType = 'chat') {
        return this.request(`/projects/${projectUuid}/messages`, {
            method: 'POST',
            body: {
                message,
                action_type: actionType,
            },
        });
    }

    /* ============================================
       Export
       ============================================ */

    async exportPDF(projectUuid) {
        return this.request(`/projects/${projectUuid}/export/pdf`, {
            method: 'POST',
        });
    }

    async exportWord(projectUuid) {
        return this.request(`/projects/${projectUuid}/export/word`, {
            method: 'POST',
        });
    }

    /*
     * Optional helper for opening export URLs returned by backend.
     * Works if ExportController returns { url: "..." } or { download_url: "..." }.
     */
    openExport(result) {
        const url = result?.download_url || result?.url || result?.path;
        if (url) {
            window.open(url, '_blank');
        }
        return result;
    }

    /* ============================================
       Credits
       ============================================ */

    async getCredits() {
        return this.request('/credits/balance');
    }

    /* ============================================
       Full Guided Workflow Helper
       ============================================ */

    /*
     * Optional convenience method for dashboard flow:
     *
     * 1. Create project
     * 2. Upload proposal if provided
     * 3. Build research profile
     *
     * It does not approve profile or generate sections automatically.
     * The user should review the profile in workspace first.
     */
    async createResearchProjectWithProfile(data) {
        const {
            title,
            type = 'thesis',
            citation_style = 'APA7',
            description = '',
            research_question = '',
            proposalFile = null,
        } = data;

        const project = await this.createProject({
            title,
            type,
            citation_style,
            description,
            research_question,
        });

        const projectUuid = project.uuid;
        const projectId = project.id;

        let upload = null;
        let profile = null;

        if (proposalFile) {
            upload = await this.uploadProposal(projectId || projectUuid, proposalFile, proposalFile.name);
            profile = await this.buildResearchProfile(projectUuid);
        }

        return {
            project,
            upload,
            profile,
        };
    }
}

// Create global instance
window.NuruAPI = new NuruAPI();

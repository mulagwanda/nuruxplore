/* ============================================
   NuruXplore - Workspace Application
   ============================================ */

document.addEventListener('alpine:init', () => {
    Alpine.data('workspaceApp', () => ({
        // State
        projectId: null,
        projectTitle: '',
        citationStyle: 'APA 7',
        wordCount: 0,
        credits: 0,
        messages: [],
        sections: [],
        sources: [],
        inputMessage: '',
        isLoading: false,
        currentView: 'document',
        api: window.NuruAPI,

        async init() {
            const urlParams = new URLSearchParams(window.location.search);
            this.projectId = urlParams.get('project');
            
            if (this.projectId) {
                await this.loadAll();
            }
        },

        async loadAll() {
            await Promise.all([
                this.loadProject(),
                this.loadCredits(),
            ]);
        },

        async loadProject() {
            try {
                const [projectRes, sectionsRes, sourcesRes, messagesRes] = await Promise.all([
                    this.api.getProject(this.projectId),
                    this.api.getSections(this.projectId),
                    this.api.getSources(this.projectId),
                    this.api.getMessages(this.projectId),
                ]);

                this.projectTitle = projectRes.project?.title || '';
                this.citationStyle = projectRes.project?.citation_style || 'APA 7';
                this.wordCount = sectionsRes.total_word_count || 0;
                this.sections = sectionsRes.sections || [];
                this.sources = sourcesRes.sources || [];
                this.messages = messagesRes.messages || [];
            } catch (error) {
                console.error('Failed to load project:', error);
                this.addSystemMessage('Failed to load project. Please refresh.');
            }
        },

        async loadCredits() {
            try {
                const data = await this.api.getCredits();
                this.credits = data.balance || 0;
            } catch (error) {
                console.error('Failed to load credits:', error);
            }
        },

        async sendMessage() {
            if (!this.inputMessage.trim() || this.isLoading) return;

            const message = this.inputMessage;
            this.inputMessage = '';
            this.isLoading = true;

            this.messages.push({
                role: 'user',
                content: message,
                created_at: new Date().toLocaleTimeString(),
            });

            this.scrollChat();

            try {
                const data = await this.api.sendMessage(this.projectId, message);
                
                this.messages.push({
                    role: 'assistant',
                    content: data.ai_message.content,
                    created_at: data.ai_message.created_at,
                });
                
                this.credits = data.credits_remaining;
            } catch (error) {
                this.addSystemMessage(`Error: ${error.message}`);
            }

            this.isLoading = false;
            this.scrollChat();
        },

        async generateOutline() {
            if (this.isLoading) return;
            this.isLoading = true;
            this.addSystemMessage('Generating outline...');

            try {
                const data = await this.api.generateOutline(this.projectId, this.projectTitle);
                
                this.addSystemMessage('✅ Outline generated!');
                this.credits = data.credits_remaining;
                await this.loadProject();
            } catch (error) {
                this.addSystemMessage(`Error: ${error.message}`);
            }

            this.isLoading = false;
            this.scrollChat();
        },

        async generateSection(sectionId) {
            if (this.isLoading) return;
            this.isLoading = true;
            this.addSystemMessage('Generating section content...');

            try {
                const data = await this.api.generateSection(sectionId);
                this.addSystemMessage('✅ Section generated!');
                this.credits = data.credits_remaining;
                await this.loadProject();
            } catch (error) {
                this.addSystemMessage(`Error: ${error.message}`);
            }

            this.isLoading = false;
            this.scrollChat();
        },

        async exportPDF() {
            try {
                const data = await this.api.exportPDF(this.projectId);
                if (data.download_url) {
                    window.open(data.download_url, '_blank');
                    this.addSystemMessage('📄 PDF exported!');
                }
            } catch (error) {
                this.addSystemMessage(`Export failed: ${error.message}`);
            }
        },

        addSystemMessage(content) {
            this.messages.push({
                role: 'system',
                content,
            });
        },

        renderMarkdown(content) {
            if (!content) return '';
            if (typeof marked !== 'undefined') {
                return marked.parse(content);
            }
            return content.replace(/\n/g, '<br>');
        },

        scrollChat() {
            this.$nextTick(() => {
                const chatBody = this.$refs.chatBody;
                if (chatBody) {
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            });
        },
    }));
});
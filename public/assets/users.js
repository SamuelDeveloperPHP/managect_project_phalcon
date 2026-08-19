const { createApp } = Vue;
createApp({
    data: () => ({
        users: [],
        companies: [],
        audits: [],
        search: '',
        loading: false,
        modal: false,
        isMaster: false,
        form: {
            id: null,
            company_id: null,
            name: '',
            email: '',
            cpf: '',
            role: 'user',
            password: '',
            permissions: {
                can_manage_projects: true,
                can_view_reports: true
            }
        }
    }),
    methods: {
        async api(url, o = {}) {
            o.headers = {
                ...(o.headers || {}),
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content
            };
            const r = await fetch(url, o), d = await r.json();
            if (!r.ok) throw Error(d.message || 'Erro na operação.');
            return d;
        },
        async loadUsers() {
            this.loading = true;
            try {
                const data = await this.api('/api/users?q=' + encodeURIComponent(this.search));
                this.users = data.users;
                if (Array.isArray(data.companies)) {
                    this.companies = data.companies;
                }
            } catch (e) {
                Swal.fire('Erro', e.message, 'error');
            } finally {
                this.loading = false;
            }
        },
        async loadAudits() {
            try {
                this.audits = (await this.api('/api/audits')).audits;
            } catch (e) { }
        },
        openCreate() {
            this.form = {
                id: null,
                company_id: this.companies.length ? this.companies[0].id : null,
                name: '',
                email: '',
                cpf: '',
                role: 'user',
                password: '',
                permissions: {
                    can_manage_projects: true,
                    can_view_reports: true
                }
            };
            this.modal = true;
        },
        openEdit(u) {
            const perms = u.permissions || {};
            this.form = {
                id: u.id,
                company_id: u.company_id,
                name: u.name,
                email: u.email,
                cpf: u.cpf || '',
                role: u.role,
                password: '',
                permissions: {
                    can_manage_projects: perms.can_manage_projects !== false,
                    can_view_reports: perms.can_view_reports !== false
                }
            };
            this.modal = true;
        },
        roleLabel(role) {
            if (role === 'master') return 'Master';
            if (role === 'admin') return 'Administrador';
            return 'Usuário';
        },
        async save() {
            try {
                const isEdit = !!this.form.id;
                const d = await this.api(isEdit ? '/api/users/' + this.form.id : '/api/users', {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(this.form)
                });
                this.modal = false;
                await this.refresh();
                Swal.fire('Sucesso', d.message, 'success');
            } catch (e) {
                Swal.fire('Erro', e.message, 'error');
            }
        },
        async toggle(u) {
            const c = await Swal.fire({ title: u.is_active ? 'Bloquear usuário?' : 'Desbloquear usuário?', icon: 'warning', showCancelButton: true });
            if (!c.isConfirmed) return;
            try {
                const d = await this.api(`/api/users/${u.id}/${u.is_active ? 'block' : 'unblock'}`, { method: 'POST' });
                await this.refresh();
                Swal.fire('Concluído', d.message, 'success');
            } catch (e) {
                Swal.fire('Erro', e.message, 'error');
            }
        },
        async remove(u) {
            const c = await Swal.fire({ title: 'Excluir usuário?', text: u.name, icon: 'warning', showCancelButton: true });
            if (!c.isConfirmed) return;
            try {
                const d = await this.api('/api/users/' + u.id, { method: 'DELETE' });
                await this.refresh();
                Swal.fire('Concluído', d.message, 'success');
            } catch (e) {
                Swal.fire('Erro', e.message, 'error');
            }
        },
        async refresh() {
            await this.loadUsers();
            await this.loadAudits();
        }
    },
    mounted() {
        this.isMaster = this.$el.dataset.master === '1';
        this.refresh();
    }
}).mount('#users-app');

<template>
    <div class="admin-requests-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Заявки на администратора</h1>
                <p class="text-muted-foreground mt-1">Управление заявками на назначение администратором</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-card rounded-lg border border-border p-4">
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-2">Статус</label>
                    <select v-model="filters.status" @change="fetchRequests" class="w-full h-10 px-3 border border-border rounded-lg bg-background">
                        <option value="">Все</option>
                        <option value="pending">Ожидают</option>
                        <option value="approved">Одобрены</option>
                        <option value="rejected">Отклонены</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-2">Бот</label>
                    <select v-model="filters.bot_id" @change="fetchRequests" class="w-full h-10 px-3 border border-border rounded-lg bg-background">
                        <option value="">Все боты</option>
                        <option v-for="bot in bots" :key="bot.id" :value="bot.id">{{ bot.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка заявок...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Requests Table -->
        <div v-if="!loading && !error" class="bg-card rounded-lg border border-border overflow-hidden">
            <div v-if="requests.length === 0" class="text-center py-12 text-muted-foreground">
                Заявки не найдены
            </div>

            <div v-else class="divide-y divide-border">
                <div
                    v-for="request in requests"
                    :key="request.id"
                    class="p-6 hover:bg-muted/10 transition-colors"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-semibold">{{ request.full_name }}</h3>
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded',
                                        request.status === 'pending' ? 'bg-yellow-500/10 text-yellow-600' :
                                        request.status === 'approved' ? 'bg-green-500/10 text-green-600' :
                                        'bg-red-500/10 text-red-600'
                                    ]"
                                >
                                    {{ request.status === 'pending' ? 'Ожидает' : request.status === 'approved' ? 'Одобрена' : 'Отклонена' }}
                                </span>
                            </div>
                            <div class="space-y-1 text-sm text-muted-foreground">
                                <p>Telegram ID: {{ request.telegram_user_id }}</p>
                                <p v-if="request.username">Username: @{{ request.username }}</p>
                                <p v-if="request.bot">Бот: {{ request.bot.name }}</p>
                                <p>Создана: {{ formatDate(request.created_at) }}</p>
                                <p v-if="request.approved_at">Обработана: {{ formatDate(request.approved_at) }}</p>
                                <p v-if="request.approver">Обработал: {{ request.approver.name }}</p>
                                <p v-if="request.admin_notes" class="mt-2 text-foreground">
                                    <strong>Примечание:</strong> {{ request.admin_notes }}
                                </p>
                            </div>
                        </div>
                        <div v-if="request.status === 'pending'" class="flex gap-2 ml-4">
                            <button
                                @click="showApproveModal(request)"
                                class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm"
                            >
                                Одобрить
                            </button>
                            <button
                                @click="showRejectModal(request)"
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm"
                            >
                                Отклонить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost } from '../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'AdminRequests',
    setup() {
        const loading = ref(false)
        const error = ref(null)
        const requests = ref([])
        const bots = ref([])
        const filters = ref({
            status: '',
            bot_id: '',
        })

        const fetchBots = async () => {
            try {
                const response = await apiGet('/bots')
                if (response.ok) {
                    const data = await response.json()
                    bots.value = data.data || []
                }
            } catch (err) {
                console.error('Error fetching bots:', err)
            }
        }

        const fetchRequests = async () => {
            loading.value = true
            error.value = null
            try {
                const params = new URLSearchParams()
                if (filters.value.status) {
                    params.append('status', filters.value.status)
                }
                if (filters.value.bot_id) {
                    params.append('bot_id', filters.value.bot_id)
                }

                const response = await apiGet(`/admin-requests?${params.toString()}`)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки заявок')
                }

                const data = await response.json()
                requests.value = data.data?.requests || []
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки заявок'
            } finally {
                loading.value = false
            }
        }

        const showApproveModal = async (request) => {
            const { value: formValues } = await Swal.fire({
                title: 'Одобрить заявку',
                html: `
                    <input id="swal-email" class="swal2-input" placeholder="Email *" required>
                    <input id="swal-name" class="swal2-input" placeholder="Имя (необязательно)">
                    <input id="swal-password" class="swal2-input" type="password" placeholder="Пароль (необязательно, будет сгенерирован)">
                    <textarea id="swal-notes" class="swal2-textarea" placeholder="Примечание (необязательно)"></textarea>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Одобрить',
                cancelButtonText: 'Отмена',
                preConfirm: () => {
                    const email = document.getElementById('swal-email').value
                    const name = document.getElementById('swal-name').value
                    const password = document.getElementById('swal-password').value
                    const notes = document.getElementById('swal-notes').value

                    if (!email) {
                        Swal.showValidationMessage('Email обязателен')
                        return false
                    }

                    return {
                        email,
                        name: name || null,
                        password: password || null,
                        admin_notes: notes || null,
                    }
                },
            })

            if (formValues) {
                await approveRequest(request.id, formValues)
            }
        }

        const showRejectModal = async (request) => {
            const { value: formValues } = await Swal.fire({
                title: 'Отклонить заявку',
                html: `
                    <textarea id="swal-notes" class="swal2-textarea" placeholder="Причина отклонения (необязательно)"></textarea>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Отклонить',
                cancelButtonText: 'Отмена',
                preConfirm: () => {
                    const notes = document.getElementById('swal-notes').value
                    return {
                        admin_notes: notes || null,
                    }
                },
            })

            if (formValues) {
                await rejectRequest(request.id, formValues)
            }
        }

        const approveRequest = async (id, data) => {
            try {
                const response = await apiPost(`/admin-requests/${id}/approve`, data)
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}))
                    throw new Error(errorData.message || 'Ошибка одобрения заявки')
                }

                await Swal.fire({
                    title: 'Успешно',
                    text: 'Заявка одобрена, пользователю назначена роль администратора',
                    icon: 'success',
                })

                fetchRequests()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка одобрения заявки',
                    icon: 'error',
                })
            }
        }

        const rejectRequest = async (id, data) => {
            try {
                const response = await apiPost(`/admin-requests/${id}/reject`, data)
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}))
                    throw new Error(errorData.message || 'Ошибка отклонения заявки')
                }

                await Swal.fire({
                    title: 'Успешно',
                    text: 'Заявка отклонена',
                    icon: 'success',
                })

                fetchRequests()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка отклонения заявки',
                    icon: 'error',
                })
            }
        }

        const formatDate = (date) => {
            if (!date) return ''
            return new Date(date).toLocaleString('ru-RU')
        }

        onMounted(() => {
            fetchBots()
            fetchRequests()
        })

        return {
            loading,
            error,
            requests,
            bots,
            filters,
            fetchRequests,
            showApproveModal,
            showRejectModal,
            formatDate,
        }
    },
}
</script>


<template>
    <div class="consultation-table space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-semibold">Заявки на консультацию</h2>
        </div>

        <!-- Filters -->
        <div class="bg-muted/30 rounded-lg border border-border p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Статус</label>
                    <select
                        v-model="filters.status"
                        @change="fetchConsultations"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    >
                        <option value="">Все</option>
                        <option value="new">Новые</option>
                        <option value="in_progress">В работе</option>
                        <option value="closed">Закрытые</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Дата от</label>
                    <input
                        v-model="filters.date_from"
                        type="date"
                        @change="fetchConsultations"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Дата до</label>
                    <input
                        v-model="filters.date_to"
                        type="date"
                        @change="fetchConsultations"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>
                <div class="flex items-end">
                    <button
                        @click="resetFilters"
                        class="w-full h-10 px-4 border border-border bg-background hover:bg-muted/10 rounded-lg"
                    >
                        Сбросить
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12">
            <p class="text-muted-foreground">Загрузка заявок...</p>
        </div>

        <!-- Table -->
        <div v-if="!loading" class="bg-background rounded-lg border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/30 border-b border-border">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Имя</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Телефон</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Статус</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase">Дата</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="consultation in consultations" :key="consultation.id" class="hover:bg-muted/10">
                            <td class="px-6 py-4 text-sm">{{ consultation.id }}</td>
                            <td class="px-6 py-4 text-sm">{{ consultation.name }}</td>
                            <td class="px-6 py-4 text-sm">{{ consultation.phone }}</td>
                            <td class="px-6 py-4">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-md',
                                        getStatusClass(consultation.status)
                                    ]"
                                >
                                    {{ getStatusLabel(consultation.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">{{ formatDate(consultation.created_at) }}</td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    @click="showDetail(consultation)"
                                    class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded"
                                >
                                    Детали
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="total > perPage" class="px-6 py-4 border-t border-border flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Показано {{ (currentPage - 1) * perPage + 1 }} - {{ Math.min(currentPage * perPage, total) }} из {{ total }}
                </div>
                <div class="flex gap-2">
                    <button
                        @click="currentPage--"
                        :disabled="currentPage === 1"
                        class="px-4 py-2 border border-border rounded-lg disabled:opacity-50"
                    >
                        Назад
                    </button>
                    <button
                        @click="currentPage++"
                        :disabled="currentPage * perPage >= total"
                        class="px-4 py-2 border border-border rounded-lg disabled:opacity-50"
                    >
                        Вперед
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <ConsultationDetail
            v-if="selectedConsultation"
            :consultation="selectedConsultation"
            :bot-id="botId"
            @close="selectedConsultation = null"
            @updated="fetchConsultations"
        />
    </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue'
import { apiGet } from '../../utils/api'
import ConsultationDetail from './ConsultationDetail.vue'

export default {
    name: 'ConsultationTable',
    components: {
        ConsultationDetail,
    },
    props: {
        botId: {
            type: [String, Number],
            required: true,
        },
    },
    setup(props) {
        const loading = ref(false)
        const consultations = ref([])
        const selectedConsultation = ref(null)
        const currentPage = ref(1)
        const perPage = ref(20)
        const total = ref(0)

        const filters = ref({
            status: '',
            date_from: '',
            date_to: '',
        })

        const fetchConsultations = async () => {
            loading.value = true
            try {
                const params = {
                    page: currentPage.value,
                    per_page: perPage.value,
                    ...filters.value,
                }

                const response = await apiGet(`/bot-management/${props.botId}/consultations`, params)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки заявок')
                }

                const data = await response.json()
                consultations.value = data.data.consultations || []
                total.value = data.data.total || 0
            } catch (err) {
                console.error('Error fetching consultations:', err)
            } finally {
                loading.value = false
            }
        }

        const resetFilters = () => {
            filters.value = {
                status: '',
                date_from: '',
                date_to: '',
            }
            currentPage.value = 1
            fetchConsultations()
        }

        const showDetail = (consultation) => {
            selectedConsultation.value = consultation
        }

        const getStatusLabel = (status) => {
            const labels = {
                new: 'Новая',
                in_progress: 'В работе',
                closed: 'Закрыта',
            }
            return labels[status] || status
        }

        const getStatusClass = (status) => {
            const classes = {
                new: 'bg-blue-500/10 text-blue-500',
                in_progress: 'bg-yellow-500/10 text-yellow-500',
                closed: 'bg-green-500/10 text-green-500',
            }
            return classes[status] || 'bg-gray-500/10 text-gray-500'
        }

        const formatDate = (date) => {
            return new Date(date).toLocaleDateString('ru-RU', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            })
        }

        watch(currentPage, () => {
            fetchConsultations()
        })

        onMounted(() => {
            fetchConsultations()
        })

        return {
            loading,
            consultations,
            selectedConsultation,
            currentPage,
            perPage,
            total,
            filters,
            fetchConsultations,
            resetFilters,
            showDetail,
            getStatusLabel,
            getStatusClass,
            formatDate,
        }
    },
}
</script>


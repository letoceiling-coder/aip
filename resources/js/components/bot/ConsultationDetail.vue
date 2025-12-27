<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-background border-b border-border p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Детали заявки #{{ consultation.id }}</h3>
                    <button @click="$emit('close')" class="text-muted-foreground hover:text-foreground">
                        ✕
                    </button>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <!-- User Info -->
                <div v-if="consultation.user">
                    <h4 class="text-sm font-medium text-muted-foreground mb-2">Пользователь</h4>
                    <div class="bg-muted/30 rounded-lg p-4">
                        <p class="text-sm">ID: {{ consultation.user.telegram_user_id }}</p>
                        <p v-if="consultation.user.username" class="text-sm">@{{ consultation.user.username }}</p>
                        <p v-if="consultation.user.first_name" class="text-sm">{{ consultation.user.first_name }} {{ consultation.user.last_name || '' }}</p>
                    </div>
                </div>

                <!-- Consultation Info -->
                <div>
                    <h4 class="text-sm font-medium text-muted-foreground mb-2">Информация о заявке</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Имя</label>
                            <p class="text-sm">{{ consultation.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Телефон</label>
                            <p class="text-sm">{{ consultation.phone }}</p>
                        </div>
                        <div v-if="consultation.description">
                            <label class="block text-xs text-muted-foreground mb-1">Описание</label>
                            <p class="text-sm whitespace-pre-wrap">{{ consultation.description }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Статус</label>
                            <select
                                v-model="form.status"
                                class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                            >
                                <option value="new">Новая</option>
                                <option value="in_progress">В работе</option>
                                <option value="closed">Закрыта</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Примечания администратора</label>
                            <textarea
                                v-model="form.admin_notes"
                                rows="4"
                                class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                                placeholder="Введите примечания..."
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-1">Дата создания</label>
                            <p class="text-sm">{{ formatDate(consultation.created_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-4 border-t border-border">
                    <button
                        @click="$emit('close')"
                        class="flex-1 h-10 px-4 border border-border bg-background/50 hover:bg-accent/10 rounded-lg"
                    >
                        Отмена
                    </button>
                    <button
                        @click="saveConsultation"
                        :disabled="saving"
                        class="flex-1 h-10 px-4 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-lg disabled:opacity-50"
                    >
                        {{ saving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiPut } from '../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'ConsultationDetail',
    props: {
        consultation: {
            type: Object,
            required: true,
        },
        botId: {
            type: [String, Number],
            required: true,
        },
    },
    emits: ['close', 'updated'],
    setup(props, { emit }) {
        const saving = ref(false)
        const form = ref({
            status: props.consultation.status,
            admin_notes: props.consultation.admin_notes || '',
        })

        const saveConsultation = async () => {
            saving.value = true
            try {
                const response = await apiPut(`/bot-management/${props.botId}/consultations/${props.consultation.id}`, form.value)
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}))
                    throw new Error(errorData.message || 'Ошибка сохранения')
                }

                await Swal.fire({
                    title: 'Сохранено',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                })

                emit('updated')
                emit('close')
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения',
                    icon: 'error',
                    confirmButtonText: 'ОК',
                })
            } finally {
                saving.value = false
            }
        }

        const formatDate = (date) => {
            return new Date(date).toLocaleString('ru-RU')
        }

        return {
            form,
            saving,
            saveConsultation,
            formatDate,
        }
    },
}
</script>


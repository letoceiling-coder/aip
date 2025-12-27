<template>
    <div class="bot-settings-form space-y-6">
        <h2 class="text-2xl font-semibold">Настройки бота</h2>

        <form @submit.prevent="saveSettings" class="space-y-6">
            <!-- Основные настройки -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <h3 class="text-lg font-semibold">Основные настройки</h3>

                <div>
                    <label class="block text-sm font-medium mb-2">ID канала</label>
                    <input
                        v-model.number="form.required_channel_id"
                        type="number"
                        placeholder="-1001234567890"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                        ID канала (можно получить через бота @userinfobot)
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Username канала</label>
                    <input
                        v-model="form.required_channel_username"
                        type="text"
                        placeholder="aip_channel"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                        Username канала без символа @
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Telegram ID администраторов</label>
                    <div class="space-y-2">
                        <div
                            v-for="(adminId, index) in form.admin_telegram_ids"
                            :key="index"
                            class="flex gap-2"
                        >
                            <input
                                v-model.number="form.admin_telegram_ids[index]"
                                type="number"
                                placeholder="123456789"
                                class="flex-1 h-10 px-3 border border-border rounded-lg bg-background"
                            />
                            <button
                                type="button"
                                @click="removeAdmin(index)"
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg"
                            >
                                Удалить
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="addAdmin"
                            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg"
                        >
                            + Добавить администратора
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Ссылка на Яндекс Карты</label>
                    <input
                        v-model="form.yandex_maps_url"
                        type="url"
                        placeholder="https://yandex.ru/maps/org/..."
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Приветственное сообщение</label>
                    <textarea
                        v-model="form.welcome_message"
                        rows="6"
                        placeholder="Добро пожаловать..."
                        class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                    ></textarea>
                </div>
            </div>

            <!-- Дополнительные настройки -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <h3 class="text-lg font-semibold">Дополнительные настройки</h3>

                <div>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.other_settings.phone_validation_strict"
                            type="checkbox"
                            class="w-4 h-4"
                        />
                        <span>Строгая валидация телефона</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Максимальная длина описания</label>
                    <input
                        v-model.number="form.other_settings.max_description_length"
                        type="number"
                        min="10"
                        max="5000"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Таймаут проверки подписки (сек)</label>
                    <input
                        v-model.number="form.other_settings.subscription_check_timeout"
                        type="number"
                        min="1"
                        max="30"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-4">
                <button
                    type="submit"
                    :disabled="saving"
                    class="flex-1 h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl disabled:opacity-50"
                >
                    {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPut } from '../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'BotSettingsForm',
    props: {
        botId: {
            type: [String, Number],
            required: true,
        },
    },
    emits: ['updated'],
    setup(props, { emit }) {
        const loading = ref(false)
        const saving = ref(false)
        const form = ref({
            required_channel_id: null,
            required_channel_username: '',
            admin_telegram_ids: [],
            yandex_maps_url: '',
            welcome_message: '',
            other_settings: {
                phone_validation_strict: false,
                max_description_length: 1000,
                subscription_check_timeout: 5,
            },
        })

        const fetchSettings = async () => {
            loading.value = true
            try {
                const response = await apiGet(`/bot-management/${props.botId}/settings`)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки настроек')
                }

                const data = await response.json()
                if (data.success && data.data) {
                    form.value = {
                        required_channel_id: data.data.required_channel_id || null,
                        required_channel_username: data.data.required_channel_username || '',
                        admin_telegram_ids: data.data.admin_telegram_ids || [],
                        yandex_maps_url: data.data.yandex_maps_url || '',
                        welcome_message: data.data.welcome_message || '',
                        other_settings: data.data.settings?.other_settings || {
                            phone_validation_strict: false,
                            max_description_length: 1000,
                            subscription_check_timeout: 5,
                        },
                    }
                }
            } catch (err) {
                console.error('Error fetching settings:', err)
            } finally {
                loading.value = false
            }
        }

        const saveSettings = async () => {
            saving.value = true
            try {
                const response = await apiPut(`/bot-management/${props.botId}/settings`, {
                    ...form.value,
                    settings: {
                        other_settings: form.value.other_settings,
                    },
                })

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}))
                    throw new Error(errorData.message || 'Ошибка сохранения настроек')
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
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения настроек',
                    icon: 'error',
                    confirmButtonText: 'ОК',
                })
            } finally {
                saving.value = false
            }
        }

        const addAdmin = () => {
            form.value.admin_telegram_ids.push(null)
        }

        const removeAdmin = (index) => {
            form.value.admin_telegram_ids.splice(index, 1)
        }

        onMounted(() => {
            fetchSettings()
        })

        return {
            loading,
            saving,
            form,
            fetchSettings,
            saveSettings,
            addAdmin,
            removeAdmin,
        }
    },
}
</script>


<?php

namespace Cmgmyr\Messenger;

use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Models;
use Cmgmyr\Messenger\Models\ConversationParticipant;
use Cmgmyr\Messenger\Models\Conversation;
use Illuminate\Support\ServiceProvider;

class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->offerPublishing();
        $this->setMessengerModels();
        $this->setUserModel();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->configure();
    }

    /**
     * Setup the configuration for Messenger.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            base_path('vendor/cmgmyr/messenger/config/config.php'),
            'messenger'
        );
    }

    /**
     * Setup the resource publishing groups for Messenger.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                base_path('vendor/cmgmyr/messenger/config/config.php') => config_path('messenger.php'),
            ], 'config');

            $this->publishes([
                base_path('vendor/cmgmyr/messenger/migrations') => base_path('database/migrations'),
            ], 'migrations');
        }
    }

    /**
     * Define Messenger's models in registry.
     *
     * @return void
     */
    protected function setMessengerModels()
    {
        $config = $this->app->make('config');

        Models::setMessageModel($config->get('messenger.message_model', Message::class));
        Models::setConversationModel($config->get('messenger.conversation_model', Conversation::class));
        Models::setConversationParticipantModel($config->get('messenger.participant_model', ConversationParticipant::class));

        Models::setTables([
            'messages' => $config->get('messenger.messages_table', Models::message()->getTable()),
            'conversation_participants' => $config->get('messenger.conversation_participants_table', Models::conversationparticipant()->getTable()),
            'conversations' => $config->get('messenger.conversations_table', Models::conversation()->getTable()),
        ]);
    }

    /**
     * Define User model in Messenger's model registry.
     *
     * @return void
     */
    protected function setUserModel()
    {
        $config = $this->app->make('config');

        $model = $config->get('messenger.user_model', function () use ($config) {
            return $config->get('auth.providers.users.model', $config->get('auth.model'));
        });

        Models::setUserModel($model);

        Models::setTables([
            'users' => (new $model)->getTable(),
        ]);
    }
}

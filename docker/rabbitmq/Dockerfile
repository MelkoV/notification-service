FROM rabbitmq:4.1-management-alpine

ARG DELAYED_MESSAGE_PLUGIN_VERSION=4.1.0

ADD https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/v${DELAYED_MESSAGE_PLUGIN_VERSION}/rabbitmq_delayed_message_exchange-${DELAYED_MESSAGE_PLUGIN_VERSION}.ez /plugins/

RUN chmod 0644 /plugins/rabbitmq_delayed_message_exchange-${DELAYED_MESSAGE_PLUGIN_VERSION}.ez \
    && chown rabbitmq:rabbitmq /plugins/rabbitmq_delayed_message_exchange-${DELAYED_MESSAGE_PLUGIN_VERSION}.ez

RUN rabbitmq-plugins enable --offline rabbitmq_delayed_message_exchange

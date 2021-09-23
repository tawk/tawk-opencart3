FROM bitnami/opencart:3.0.3-6-debian-10-r64

ENV OPENCART_ROOT_DIR=/opt/bitnami/opencart

# Copy setup script
ADD setup.sh /tmp/build/setup.sh

# RUN setup
RUN chmod +x /tmp/build/setup.sh
RUN /tmp/build/setup.sh

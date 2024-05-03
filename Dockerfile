# Use Python 2.7.18 slim
FROM python:2.7.18-slim

# Set working dir
WORKDIR /app

# Copy contents to dir
COPY . /app

# Command to run
RUN pip install flask pillow
CMD python wsgi.py

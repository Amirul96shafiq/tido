# Ollama Setup Guide (GPU Acceleration)

This guide documents how to configure Ollama with GPU acceleration on your host machine to run OCR models for receipt parsing at zero cost.

## Prerequisites
- NVIDIA Graphics Card (RTX 4060 or higher confirmed)
- Docker Desktop with CUDA support
- NVIDIA Container Toolkit

---

## Step 1: Install NVIDIA Container Toolkit
Before running Docker containers with GPU access, you must register the NVIDIA driver with Docker.

### On Windows
Docker Desktop on Windows supports WSL 2 GPU paravirtualization natively. Make sure:
1. WSL 2 is installed and selected in Docker Desktop options.
2. The latest NVIDIA Game Ready or Studio Driver is installed on the host.

### On Linux
Run the following commands to install the Toolkit:
```bash
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg
curl -s -L https://nvidia.github.io/libnvidia-container/stable/deb/nvidia-container-toolkit.list | \
  sed 's/\(deb\s\[\)/\1signed-by=\/usr/share/keyrings\/nvidia-container-toolkit-keyring.gpg /' | \
  sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit
sudo systemctl restart docker
```

---

## Step 2: Spin up the Containers
Run Sail/Docker Compose to launch the stack:
```bash
./vendor/bin/sail up -d
```

---

## Step 3: Pull the Vision Model
Ollama needs to download the `minicpm-v` (or `llava`) model to perform OCR on receipts.

```bash
docker exec -it trackall-ollama-1 ollama pull minicpm-v
```

---

## Step 4: Verify the Installation
To confirm that Ollama is running and has access to your GPU:

### 1. Test Endpoint
Send a curl request to ensure the API is reachable:
```bash
curl http://localhost:11434/api/tags
```

### 2. Verify GPU usage
Run `nvidia-smi` on the host while uploading a receipt. You should see `ollama` or `ollama_llama_server` allocate memory and consume GPU compute during parsing.

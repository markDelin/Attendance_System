import React, { useState, useEffect } from 'react';
import { StyleSheet, Text, View, TextInput, TouchableOpacity, SafeAreaView, KeyboardAvoidingView, Platform, StatusBar } from 'react-native';
import { WebView } from 'react-native-webview';
import AsyncStorage from '@react-native-async-storage/async-storage';

export default function App() {
  const [ipAddress, setIpAddress] = useState('');
  const [savedIp, setSavedIp] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    loadSavedIp();
  }, []);

  const loadSavedIp = async () => {
    try {
      const ip = await AsyncStorage.getItem('@server_ip');
      if (ip !== null) {
        setSavedIp(ip);
      }
    } catch (e) {
      console.error('Failed to load IP');
    } finally {
      setIsLoading(false);
    }
  };

  const saveIpConfig = async () => {
    const cleanIp = ipAddress.trim();
    if (!cleanIp) return;
    try {
      await AsyncStorage.setItem('@server_ip', cleanIp);
      setSavedIp(cleanIp);
    } catch (e) {
      console.error('Failed to save IP');
    }
  };

  const resetIpConfig = async () => {
    try {
      await AsyncStorage.removeItem('@server_ip');
      setSavedIp(null);
      setIpAddress('');
    } catch (e) {
      console.error('Failed to reset IP');
    }
  };

  if (isLoading) {
    return <View style={styles.container}><Text>Starting Attendance MobileApp...</Text></View>;
  }

  // If we have a saved IP, render the webview
  if (savedIp) {
    return (
      <View style={styles.webviewContainer}>
        <StatusBar barStyle="dark-content" backgroundColor="#f5f7fa" />
        <WebView 
          source={{ uri: `http://${savedIp}:8000` }} 
          style={styles.webview}
          onHttpError={(syntheticEvent) => {
             const { nativeEvent } = syntheticEvent;
             if (nativeEvent.statusCode === 404 || nativeEvent.statusCode >= 500) {
                 console.log("Webview Error Code:", nativeEvent.statusCode);
             }
          }}
        />
        {/* Hidden reset button at the bottom */}
        <TouchableOpacity 
          style={styles.resetButton} 
          onLongPress={resetIpConfig}
          delayLongPress={2000}>
          <Text style={styles.resetText}>Hold to Reset IP</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.setupCard}>
        <Text style={styles.title}>Attendance Mobile Node</Text>
        <Text style={styles.subtitle}>Enter the Mobile Connection IP displayed on your PC's dashboard (e.g. 192.168.1.100)</Text>
        
        <TextInput
          style={styles.input}
          placeholder="192.168.1.5"
          value={ipAddress}
          onChangeText={setIpAddress}
          keyboardType="numeric"
          autoCapitalize="none"
        />
        
        <TouchableOpacity style={styles.button} onPress={saveIpConfig}>
          <Text style={styles.buttonText}>Connect to LAN Server</Text>
        </TouchableOpacity>
        <Text style={styles.hint}>Make sure both devices are on the same Wi-Fi.</Text>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0a0a0a',
    justifyContent: 'center',
    padding: 20,
  },
  setupCard: {
    backgroundColor: '#1c1c1c',
    padding: 24,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#333',
    elevation: 5,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#ffffff',
    marginBottom: 8,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 14,
    color: '#a0aec0',
    marginBottom: 24,
    textAlign: 'center',
    lineHeight: 20,
  },
  input: {
    backgroundColor: '#2d3748',
    color: 'white',
    borderWidth: 1,
    borderColor: '#4a5568',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    marginBottom: 16,
    textAlign: 'center',
  },
  button: {
    backgroundColor: '#4299e1',
    padding: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  buttonText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 16,
  },
  hint: {
    marginTop: 16,
    fontSize: 12,
    color: '#718096',
    textAlign: 'center',
  },
  webviewContainer: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  webview: {
    flex: 1,
    marginTop: Platform.OS === 'ios' ? 40 : 0, 
  },
  resetButton: {
    position: 'absolute',
    bottom: 20,
    alignSelf: 'center',
    backgroundColor: 'rgba(0,0,0,0.8)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
  },
  resetText: {
    color: 'white',
    fontSize: 12,
  }
});
